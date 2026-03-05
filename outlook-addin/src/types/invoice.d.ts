/**
 * TypeScript interfaces for the ReadInvoice Outlook Add-in.
 */

/** Raw email data extracted from the Outlook item. */
export interface EmailData {
  /** Sender email address. */
  from: string;
  /** Sender display name. */
  fromName: string;
  /** Email subject line. */
  subject: string;
  /** Plain-text body of the email. */
  bodyText: string;
  /** List of attachments metadata. */
  attachments: AttachmentMeta[];
  /** Email message ID from Exchange. */
  messageId: string;
  /** Date the email was received. */
  receivedDateTime: string;
}

/** Metadata about an email attachment (before content is fetched). */
export interface AttachmentMeta {
  /** Attachment unique ID within the message. */
  id: string;
  /** File name of the attachment. */
  name: string;
  /** MIME content type. */
  contentType: string;
  /** Size in bytes. */
  size: number;
  /** Whether this is an inline attachment. */
  isInline: boolean;
}

/** Full attachment data including base64 content. */
export interface AttachmentData {
  /** Attachment unique ID. */
  id: string;
  /** File name. */
  name: string;
  /** MIME content type. */
  contentType: string;
  /** Size in bytes. */
  size: number;
  /** Base64-encoded file content. */
  contentBase64: string;
  /** Whether the attachment passed PDF validation. */
  isValidPdf: boolean;
}

/** Result of invoice detection heuristics. */
export interface InvoiceDetection {
  /** Whether the email is likely an invoice. */
  isInvoice: boolean;
  /** Confidence score from 0.0 to 1.0. */
  confidence: number;
  /** Detected invoice fields. */
  fields: InvoiceFields;
  /** Individual signal scores that contributed to the detection. */
  signals: DetectionSignal[];
}

/** Detected invoice-related fields extracted from the email. */
export interface InvoiceFields {
  /** Invoice number if detected. */
  invoiceNumber: string | null;
  /** Total amount if detected (as string to preserve formatting). */
  totalAmount: string | null;
  /** Currency code if detected. */
  currency: string | null;
  /** Due date if detected. */
  dueDate: string | null;
  /** Vendor/sender name. */
  vendorName: string | null;
  /** PO number if detected. */
  poNumber: string | null;
}

/** An individual detection signal contributing to the overall score. */
export interface DetectionSignal {
  /** Name of the signal. */
  name: string;
  /** Whether this signal was triggered. */
  triggered: boolean;
  /** Weight of this signal in the overall score. */
  weight: number;
  /** Matched value if applicable. */
  matchedValue?: string;
}

/** Payload sent to the backend API for ingestion. */
export interface IngestPayload {
  /** Source identifier. */
  source: "outlook-addin";
  /** Email metadata. */
  email: {
    from: string;
    fromName: string;
    subject: string;
    bodyText: string;
    messageId: string;
    receivedDateTime: string;
  };
  /** Invoice detection results. */
  detection: {
    isInvoice: boolean;
    confidence: number;
    fields: InvoiceFields;
  };
  /** PDF attachments as base64. */
  attachments: Array<{
    name: string;
    contentType: string;
    size: number;
    contentBase64: string;
  }>;
}

/** Response from the backend API. */
export interface ApiResponse {
  /** Whether the request succeeded. */
  success: boolean;
  /** Human-readable message. */
  message: string;
  /** Created invoice ID on success. */
  invoiceId?: string;
  /** Error code on failure. */
  errorCode?: string;
}

/** Configuration for the API client. */
export interface ApiClientConfig {
  /** Base URL of the backend API. */
  baseUrl: string;
  /** JWT token for authentication. */
  jwtToken: string;
  /** Request timeout in milliseconds. */
  timeoutMs: number;
  /** Maximum number of retry attempts. */
  maxRetries: number;
  /** Base delay between retries in milliseconds. */
  retryDelayMs: number;
}
