/**
 * Taskpane Main Logic
 *
 * Entry point for the ReadInvoice Outlook Add-in taskpane.
 * Initializes Office.js, reads the current email, runs invoice detection,
 * displays results, and handles the "Send to ReadInvoice" action.
 */

import "./taskpane.css";
import { readCurrentEmail } from "../services/email-reader";
import { detectInvoice } from "../services/invoice-detector";
import { getAllPdfAttachments } from "../services/attachment-handler";
import { initialize as initApiClient, sendToBackend } from "../services/api-client";
import type {
  EmailData,
  InvoiceDetection,
  AttachmentData,
  IngestPayload,
} from "../types/invoice";

/** Current state of the taskpane. */
interface TaskpaneState {
  emailData: EmailData | null;
  detection: InvoiceDetection | null;
  pdfAttachments: AttachmentData[];
  isProcessing: boolean;
}

const state: TaskpaneState = {
  emailData: null,
  detection: null,
  pdfAttachments: [],
  isProcessing: false,
};

/* ============================================================
   Office.js Initialization
   ============================================================ */

Office.onReady((info) => {
  if (info.host === Office.HostType.Outlook) {
    initializeAddin();
  }
});

/**
 * Initializes the add-in: sets up API client, binds event handlers,
 * and performs the initial email scan.
 */
function initializeAddin(): void {
  // Initialize the API client with stored configuration
  try {
    const storedToken = getStoredToken();
    if (storedToken) {
      initApiClient({
        baseUrl: "https://readinvoice.example.com",
        jwtToken: storedToken,
      });
    }
  } catch {
    // API client will report auth errors when send is attempted
  }

  // Bind button event handlers
  const sendButton = document.getElementById("send-button");
  const rescanButton = document.getElementById("rescan-button");
  const retryButton = document.getElementById("retry-button");

  if (sendButton) {
    sendButton.addEventListener("click", handleSend);
  }
  if (rescanButton) {
    rescanButton.addEventListener("click", handleRescan);
  }
  if (retryButton) {
    retryButton.addEventListener("click", handleRescan);
  }

  // Perform initial scan
  scanCurrentEmail();
}

/* ============================================================
   Core Workflow
   ============================================================ */

/**
 * Scans the current email: reads data, detects invoices, and updates the UI.
 */
async function scanCurrentEmail(): Promise<void> {
  if (state.isProcessing) {
    return;
  }

  state.isProcessing = true;
  showSection("loading-section");
  hideSection("email-info-section");
  hideSection("detection-section");
  hideSection("action-section");
  hideSection("status-section");
  hideSection("error-section");

  try {
    // Step 1: Read email data
    const emailData = await readCurrentEmail();
    state.emailData = emailData;

    // Step 2: Detect invoice
    const detection = detectInvoice(emailData);
    state.detection = detection;

    // Step 3: Get PDF attachments if this looks like an invoice
    if (detection.isInvoice) {
      const { valid, errors: _attErrors } = await getAllPdfAttachments(emailData.attachments);
      state.pdfAttachments = valid;
    } else {
      state.pdfAttachments = [];
    }

    // Step 4: Update UI
    hideSection("loading-section");
    updateEmailInfo(emailData);
    updateDetectionResults(detection);
    updateActionButtons(detection);

    showSection("email-info-section");
    showSection("detection-section");
    showSection("action-section");
  } catch (_err: unknown) {
    hideSection("loading-section");
    showError("Failed to read the current email. Please ensure an email is selected and try again.");
  } finally {
    state.isProcessing = false;
  }
}

/* ============================================================
   UI Update Functions
   ============================================================ */

/**
 * Updates the email information section with sanitized data.
 */
function updateEmailInfo(email: EmailData): void {
  setTextContent("email-subject", email.subject || "(No subject)");
  setTextContent("email-from", email.fromName ? `${email.fromName} <${email.from}>` : email.from);

  const attachmentCount = email.attachments.length;
  const pdfCount = email.attachments.filter(
    (a) => a.contentType.toLowerCase() === "application/pdf" || a.name.toLowerCase().endsWith(".pdf")
  ).length;

  let attachmentText = `${attachmentCount} attachment${attachmentCount !== 1 ? "s" : ""}`;
  if (pdfCount > 0) {
    attachmentText += ` (${pdfCount} PDF)`;
  }
  setTextContent("email-attachments", attachmentText);
}

/**
 * Updates the detection results section.
 */
function updateDetectionResults(detection: InvoiceDetection): void {
  // Update confidence bar
  const percentage = Math.round(detection.confidence * 100);
  setTextContent("confidence-value", `${percentage}%`);

  const fill = document.getElementById("confidence-fill");
  if (fill) {
    fill.style.width = `${percentage}%`;
    fill.className = "confidence-fill";
    if (percentage >= 60) {
      fill.classList.add("high");
    } else if (percentage >= 30) {
      fill.classList.add("medium");
    } else {
      fill.classList.add("low");
    }
  }

  // Update status badge
  const badge = document.getElementById("detection-status");
  if (badge) {
    badge.className = "status-badge";
    if (detection.confidence >= 0.6) {
      badge.classList.add("status-invoice");
      badge.textContent = "Likely Invoice";
    } else if (detection.confidence >= 0.3) {
      badge.classList.add("status-possible");
      badge.textContent = "Possible Invoice";
    } else {
      badge.classList.add("status-unlikely");
      badge.textContent = "Unlikely Invoice";
    }
  }

  // Update detected fields
  const fields = detection.fields;
  setTextContent("field-invoice-number", fields.invoiceNumber ?? "--");
  setTextContent("field-amount", fields.totalAmount ?? "--");
  setTextContent("field-due-date", fields.dueDate ?? "--");
  setTextContent("field-vendor", fields.vendorName ?? "--");
  setTextContent("field-po-number", fields.poNumber ?? "--");

  // Show fields container if we have any detected values
  const hasFields = fields.invoiceNumber || fields.totalAmount || fields.dueDate || fields.poNumber;
  if (hasFields) {
    showSection("detected-fields");
  } else {
    hideSection("detected-fields");
  }

  // Update signals list
  updateSignals(detection.signals);
}

/**
 * Updates the detection signals list.
 */
function updateSignals(signals: Array<{ name: string; triggered: boolean; weight: number; matchedValue?: string }>): void {
  const list = document.getElementById("signals-list");
  if (!list) return;

  list.innerHTML = "";

  for (const signal of signals) {
    const li = document.createElement("li");

    const icon = document.createElement("span");
    icon.className = `signal-icon ${signal.triggered ? "triggered" : "not-triggered"}`;
    icon.textContent = signal.triggered ? "\u2713" : "\u2717";

    const name = document.createElement("span");
    name.textContent = signal.name;

    li.appendChild(icon);
    li.appendChild(name);

    if (signal.triggered && signal.matchedValue) {
      const match = document.createElement("span");
      match.className = "signal-match";
      match.textContent = truncate(signal.matchedValue, 30);
      match.title = signal.matchedValue;
      li.appendChild(match);
    }

    list.appendChild(li);
  }

  showSection("signals-container");
}

/**
 * Updates action buttons based on detection results.
 */
function updateActionButtons(detection: InvoiceDetection): void {
  const sendButton = document.getElementById("send-button") as HTMLButtonElement | null;
  if (sendButton) {
    sendButton.disabled = !detection.isInvoice;
    sendButton.textContent = detection.isInvoice
      ? "Send to ReadInvoice"
      : "No Invoice Detected";
  }
}

/* ============================================================
   Event Handlers
   ============================================================ */

/**
 * Handles the "Send to ReadInvoice" button click.
 */
async function handleSend(): Promise<void> {
  if (state.isProcessing || !state.emailData || !state.detection) {
    return;
  }

  state.isProcessing = true;
  const sendButton = document.getElementById("send-button") as HTMLButtonElement | null;
  if (sendButton) {
    sendButton.disabled = true;
    sendButton.textContent = "Sending...";
  }

  hideSection("status-section");
  hideSection("error-section");

  try {
    const payload: IngestPayload = {
      source: "outlook-addin",
      email: {
        from: state.emailData.from,
        fromName: state.emailData.fromName,
        subject: state.emailData.subject,
        bodyText: state.emailData.bodyText,
        messageId: state.emailData.messageId,
        receivedDateTime: state.emailData.receivedDateTime,
      },
      detection: {
        isInvoice: state.detection.isInvoice,
        confidence: state.detection.confidence,
        fields: state.detection.fields,
      },
      attachments: state.pdfAttachments.map((att) => ({
        name: att.name,
        contentType: att.contentType,
        size: att.size,
        contentBase64: att.contentBase64,
      })),
    };

    const response = await sendToBackend(payload);

    if (response.success) {
      showStatus(
        `Invoice submitted successfully.${response.invoiceId ? ` ID: ${sanitizeDisplay(response.invoiceId)}` : ""}`,
        "success"
      );
    } else {
      showStatus(
        sanitizeDisplay(response.message) || "Failed to submit invoice.",
        "error"
      );
    }
  } catch {
    showStatus("An unexpected error occurred. Please try again.", "error");
  } finally {
    state.isProcessing = false;
    if (sendButton) {
      sendButton.disabled = false;
      sendButton.textContent = "Send to ReadInvoice";
    }
  }
}

/**
 * Handles the rescan button click.
 */
function handleRescan(): void {
  state.emailData = null;
  state.detection = null;
  state.pdfAttachments = [];
  scanCurrentEmail();
}

/* ============================================================
   Utility Functions
   ============================================================ */

/**
 * Shows a section by removing the hidden class.
 */
function showSection(id: string): void {
  const el = document.getElementById(id);
  if (el) {
    el.classList.remove("hidden");
  }
}

/**
 * Hides a section by adding the hidden class.
 */
function hideSection(id: string): void {
  const el = document.getElementById(id);
  if (el) {
    el.classList.add("hidden");
  }
}

/**
 * Safely sets text content on an element, preventing XSS.
 * Uses textContent (not innerHTML) to prevent injection.
 */
function setTextContent(id: string, text: string): void {
  const el = document.getElementById(id);
  if (el) {
    el.textContent = text;
  }
}

/**
 * Shows a status message with the specified type.
 */
function showStatus(message: string, type: "success" | "error" | "info" | "warning"): void {
  const statusMessage = document.getElementById("status-message");
  if (statusMessage) {
    statusMessage.textContent = message;
    statusMessage.className = `status-message ${type}`;
  }
  showSection("status-section");
}

/**
 * Shows the error section with a message.
 */
function showError(message: string): void {
  const errorMessage = document.getElementById("error-message");
  if (errorMessage) {
    errorMessage.textContent = message;
  }
  showSection("error-section");
}

/**
 * Sanitizes a string for safe display in the UI.
 */
function sanitizeDisplay(input: string): string {
  if (!input || typeof input !== "string") {
    return "";
  }
  // textContent is already safe, but we truncate to prevent UI overflow
  return truncate(input, 200);
}

/**
 * Truncates a string to the specified length.
 */
function truncate(text: string, maxLength: number): string {
  if (text.length <= maxLength) {
    return text;
  }
  return text.substring(0, maxLength) + "...";
}

/**
 * Retrieves the stored JWT token from Office roaming settings.
 */
function getStoredToken(): string | null {
  try {
    const settings = Office.context.roamingSettings;
    const token = settings.get("readinvoice_jwt") as string | undefined;
    return token ?? null;
  } catch {
    return null;
  }
}
