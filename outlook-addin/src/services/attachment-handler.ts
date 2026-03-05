/**
 * Attachment Handler Service
 *
 * Handles retrieval and validation of PDF attachments from Outlook emails.
 * Validates PDF magic bytes and manages base64 content safely.
 */

import { AttachmentMeta, AttachmentData } from "../types/invoice";
import { getAttachmentContent } from "./email-reader";

/** PDF magic bytes: "%PDF" as base64 prefix patterns. */
const PDF_MAGIC_BASE64_PREFIXES: string[] = [
  "JVBER",  // %PDF in base64
];

/** Maximum attachment size in bytes (25 MB). */
const MAX_ATTACHMENT_SIZE = 25 * 1024 * 1024;

/** PDF-related MIME types. */
const PDF_MIME_TYPES: string[] = [
  "application/pdf",
  "application/x-pdf",
  "application/acrobat",
  "application/vnd.pdf",
];

/**
 * Determines whether an attachment is a potential PDF based on metadata.
 */
export function isPdfAttachment(attachment: AttachmentMeta): boolean {
  const nameMatch = attachment.name.toLowerCase().endsWith(".pdf");
  const typeMatch = PDF_MIME_TYPES.includes(attachment.contentType.toLowerCase());
  return nameMatch || typeMatch;
}

/**
 * Validates that a base64 string represents a valid PDF by checking magic bytes.
 *
 * The PDF specification requires files to start with "%PDF-" (hex: 25 50 44 46 2D).
 * In base64, "%PDF" encodes to "JVBER".
 */
export function isValidPdfBase64(base64Content: string): boolean {
  if (!base64Content || typeof base64Content !== "string") {
    return false;
  }

  // Remove any whitespace or newlines from the base64 string
  const cleaned = base64Content.replace(/\s/g, "").trim();

  if (cleaned.length === 0) {
    return false;
  }

  // Check for PDF magic bytes in base64 encoding
  return PDF_MAGIC_BASE64_PREFIXES.some((prefix) =>
    cleaned.startsWith(prefix)
  );
}

/**
 * Validates that a raw binary buffer starts with PDF magic bytes.
 * This is used when content is decoded from base64 for additional verification.
 */
export function isValidPdfBytes(bytes: Uint8Array): boolean {
  if (!bytes || bytes.length < 5) {
    return false;
  }

  // %PDF- in ASCII: 0x25, 0x50, 0x44, 0x46, 0x2D
  return (
    bytes[0] === 0x25 &&
    bytes[1] === 0x50 &&
    bytes[2] === 0x44 &&
    bytes[3] === 0x46 &&
    bytes[4] === 0x2d
  );
}

/**
 * Validates attachment size is within acceptable limits.
 */
export function isAcceptableSize(sizeInBytes: number): boolean {
  return sizeInBytes > 0 && sizeInBytes <= MAX_ATTACHMENT_SIZE;
}

/**
 * Retrieves and validates a PDF attachment, returning its content as base64.
 *
 * @param attachment - Attachment metadata from the email.
 * @returns AttachmentData with validated content or throws on failure.
 */
export async function getPdfAttachment(
  attachment: AttachmentMeta
): Promise<AttachmentData> {
  // Validate it looks like a PDF from metadata
  if (!isPdfAttachment(attachment)) {
    throw new Error(
      `Attachment "${sanitizeForError(attachment.name)}" does not appear to be a PDF.`
    );
  }

  // Validate size
  if (!isAcceptableSize(attachment.size)) {
    throw new Error(
      `Attachment size (${attachment.size} bytes) exceeds the maximum allowed size.`
    );
  }

  // Retrieve the content via Office.js
  const contentBase64 = await getAttachmentContent(attachment.id);

  // Validate the content is actually a PDF
  const isValid = isValidPdfBase64(contentBase64);

  if (!isValid) {
    throw new Error(
      `Attachment "${sanitizeForError(attachment.name)}" failed PDF validation. ` +
      `The file does not appear to be a valid PDF document.`
    );
  }

  return {
    id: attachment.id,
    name: attachment.name,
    contentType: attachment.contentType,
    size: attachment.size,
    contentBase64,
    isValidPdf: true,
  };
}

/**
 * Processes all PDF attachments from an email, returning validated content.
 * Skips non-PDF and inline attachments. Collects errors without exposing internals.
 */
export async function getAllPdfAttachments(
  attachments: AttachmentMeta[]
): Promise<{ valid: AttachmentData[]; errors: string[] }> {
  const pdfAttachments = attachments.filter(
    (att) => !att.isInline && isPdfAttachment(att)
  );

  const valid: AttachmentData[] = [];
  const errors: string[] = [];

  for (const att of pdfAttachments) {
    try {
      const data = await getPdfAttachment(att);
      valid.push(data);
    } catch (_err: unknown) {
      // Do not log raw error details (OWASP); provide a safe message
      errors.push(
        `Could not process attachment "${sanitizeForError(att.name)}".`
      );
    }
  }

  return { valid, errors };
}

/**
 * Sanitizes a filename for safe inclusion in error messages.
 * Prevents injection of control characters or excessively long names.
 */
function sanitizeForError(name: string): string {
  if (!name || typeof name !== "string") {
    return "[unknown]";
  }

  return name
    .replace(/[^\w\s.\-()]/g, "_")
    .substring(0, 80);
}
