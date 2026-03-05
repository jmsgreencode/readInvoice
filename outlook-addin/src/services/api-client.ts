/**
 * API Client Service
 *
 * Sends invoice data to the ReadInvoice backend API.
 * Implements JWT authentication, retry logic with exponential backoff,
 * timeout handling, and OWASP-compliant error handling.
 */

import {
  ApiResponse,
  ApiClientConfig,
  IngestPayload,
} from "../types/invoice";

/** Default API client configuration. */
const DEFAULT_CONFIG: ApiClientConfig = {
  baseUrl: "https://readinvoice.example.com",
  jwtToken: "",
  timeoutMs: 30000,
  maxRetries: 3,
  retryDelayMs: 1000,
};

/** HTTP status codes that are safe to retry. */
const RETRYABLE_STATUS_CODES: number[] = [408, 429, 500, 502, 503, 504];

/** Singleton configuration, set via initialize(). */
let config: ApiClientConfig = { ...DEFAULT_CONFIG };

/**
 * Initializes the API client with configuration.
 * Must be called before making any API requests.
 */
export function initialize(overrides: Partial<ApiClientConfig>): void {
  config = {
    ...DEFAULT_CONFIG,
    ...overrides,
  };

  // Validate required fields
  if (!config.baseUrl) {
    throw new Error("API base URL is required.");
  }

  if (!config.jwtToken) {
    throw new Error("JWT token is required for authentication.");
  }

  // Ensure baseUrl does not end with a slash
  config.baseUrl = config.baseUrl.replace(/\/+$/, "");
}

/**
 * Gets the current JWT token. Returns a masked version for safe display.
 */
export function isAuthenticated(): boolean {
  return !!config.jwtToken && config.jwtToken.length > 0;
}

/**
 * Sends email invoice data to the backend for ingestion.
 *
 * @param payload - The ingestion payload containing email, detection, and attachments.
 * @returns ApiResponse from the backend.
 */
export async function sendToBackend(
  payload: IngestPayload
): Promise<ApiResponse> {
  if (!isAuthenticated()) {
    return {
      success: false,
      message: "Not authenticated. Please configure your API credentials.",
      errorCode: "AUTH_REQUIRED",
    };
  }

  // Validate payload before sending
  const validationError = validatePayload(payload);
  if (validationError) {
    return {
      success: false,
      message: validationError,
      errorCode: "VALIDATION_ERROR",
    };
  }

  const url = `${config.baseUrl}/api/emails/ingest`;

  return executeWithRetry(url, payload);
}

/**
 * Executes the API request with retry logic and exponential backoff.
 */
async function executeWithRetry(
  url: string,
  payload: IngestPayload
): Promise<ApiResponse> {
  let lastError: string = "Unknown error occurred.";

  for (let attempt = 0; attempt <= config.maxRetries; attempt++) {
    if (attempt > 0) {
      // Exponential backoff with jitter
      const delay = config.retryDelayMs * Math.pow(2, attempt - 1);
      const jitter = Math.random() * delay * 0.1;
      await sleep(delay + jitter);
    }

    try {
      const response = await fetchWithTimeout(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${config.jwtToken}`,
          "X-Source": "outlook-addin",
          "X-Request-Id": generateRequestId(),
        },
        body: JSON.stringify(payload),
      });

      // Parse response
      if (response.ok) {
        const data = await response.json() as ApiResponse;
        return {
          success: true,
          message: data.message || "Invoice submitted successfully.",
          invoiceId: data.invoiceId,
        };
      }

      // Handle specific error codes
      if (response.status === 401 || response.status === 403) {
        return {
          success: false,
          message: "Authentication failed. Please re-authenticate.",
          errorCode: "AUTH_FAILED",
        };
      }

      if (response.status === 422) {
        const errorData = await safeParseJson(response);
        return {
          success: false,
          message: errorData?.message || "The server could not process the request.",
          errorCode: "VALIDATION_ERROR",
        };
      }

      // Check if retryable
      if (!RETRYABLE_STATUS_CODES.includes(response.status)) {
        return {
          success: false,
          message: "The server returned an unexpected error. Please try again later.",
          errorCode: `HTTP_${response.status}`,
        };
      }

      lastError = `Server returned status ${response.status}.`;
    } catch (_err: unknown) {
      // Do not log the raw error (OWASP compliance)
      if (_err instanceof DOMException && _err.name === "AbortError") {
        lastError = "Request timed out.";
      } else {
        lastError = "A network error occurred.";
      }

      // Only retry on network/timeout errors
      if (attempt === config.maxRetries) {
        break;
      }
    }
  }

  return {
    success: false,
    message: `Failed after ${config.maxRetries + 1} attempts. ${lastError}`,
    errorCode: "MAX_RETRIES_EXCEEDED",
  };
}

/**
 * Performs a fetch with an AbortController-based timeout.
 */
async function fetchWithTimeout(
  url: string,
  options: RequestInit
): Promise<Response> {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), config.timeoutMs);

  try {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal,
    });
    return response;
  } finally {
    clearTimeout(timeoutId);
  }
}

/**
 * Validates the ingestion payload before sending.
 * Returns an error message string if invalid, or null if valid.
 */
function validatePayload(payload: IngestPayload): string | null {
  if (!payload) {
    return "Payload is required.";
  }

  if (payload.source !== "outlook-addin") {
    return "Invalid source identifier.";
  }

  if (!payload.email) {
    return "Email data is required.";
  }

  if (!payload.email.subject && !payload.email.bodyText) {
    return "Email must have a subject or body.";
  }

  if (!payload.email.from) {
    return "Sender email address is required.";
  }

  // Validate email format loosely
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email.from)) {
    return "Invalid sender email address format.";
  }

  // Validate attachments
  if (payload.attachments) {
    for (const att of payload.attachments) {
      if (!att.name || !att.contentBase64) {
        return "Each attachment must have a name and content.";
      }

      if (att.size > 25 * 1024 * 1024) {
        return "Attachment exceeds maximum size limit.";
      }
    }
  }

  return null;
}

/**
 * Safely parses JSON from a response, returning null on failure.
 */
async function safeParseJson(response: Response): Promise<ApiResponse | null> {
  try {
    return (await response.json()) as ApiResponse;
  } catch {
    return null;
  }
}

/**
 * Generates a unique request ID for tracing.
 */
function generateRequestId(): string {
  const timestamp = Date.now().toString(36);
  const random = Math.random().toString(36).substring(2, 10);
  return `ri-${timestamp}-${random}`;
}

/**
 * Promisified sleep utility.
 */
function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
