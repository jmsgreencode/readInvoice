/**
 * Email Reader Service
 *
 * Uses Office.js API to read the current email item's properties:
 * from, subject, body text, and attachments.
 */

import { EmailData, AttachmentMeta } from "../types/invoice";

/**
 * Reads the current Outlook mail item and extracts structured email data.
 * Requires the mailbox item to be available in the Office context.
 */
export async function readCurrentEmail(): Promise<EmailData> {
  const item = Office.context.mailbox?.item;

  if (!item) {
    throw new Error("No mail item is currently selected.");
  }

  const [bodyText, attachments] = await Promise.all([
    getBodyText(item),
    getAttachmentsMeta(item),
  ]);

  const from = item.from?.emailAddress ?? "";
  const fromName = item.from?.displayName ?? "";
  const subject = item.subject ?? "";
  const messageId = item.itemId ?? "";

  // dateTimeCreated may be available on read items
  const receivedDateTime =
    (item as Office.MessageRead).dateTimeCreated?.toISOString() ?? new Date().toISOString();

  return {
    from: sanitizeString(from),
    fromName: sanitizeString(fromName),
    subject: sanitizeString(subject),
    bodyText: sanitizeString(bodyText),
    attachments,
    messageId,
    receivedDateTime,
  };
}

/**
 * Gets the plain-text body of the current mail item.
 */
function getBodyText(item: Office.MessageRead): Promise<string> {
  return new Promise((resolve, reject) => {
    item.body.getAsync(
      Office.CoercionType.Text,
      (result: Office.AsyncResult<string>) => {
        if (result.status === Office.AsyncResultStatus.Failed) {
          reject(new Error(`Failed to read email body: ${result.error?.message ?? "Unknown error"}`));
          return;
        }
        resolve(result.value ?? "");
      }
    );
  });
}

/**
 * Gets metadata for all attachments on the current mail item.
 */
function getAttachmentsMeta(item: Office.MessageRead): Promise<AttachmentMeta[]> {
  const attachments = item.attachments ?? [];

  return Promise.resolve(
    attachments.map((att: Office.AttachmentDetails) => ({
      id: att.id,
      name: sanitizeString(att.name),
      contentType: att.contentType,
      size: att.size,
      isInline: att.isInline,
    }))
  );
}

/**
 * Gets the raw content of a specific attachment as base64.
 * Used to retrieve PDF content for invoice processing.
 */
export function getAttachmentContent(attachmentId: string): Promise<string> {
  return new Promise((resolve, reject) => {
    const item = Office.context.mailbox?.item;

    if (!item) {
      reject(new Error("No mail item is currently selected."));
      return;
    }

    item.getAttachmentContentAsync(
      attachmentId,
      (result: Office.AsyncResult<Office.AttachmentContent>) => {
        if (result.status === Office.AsyncResultStatus.Failed) {
          reject(
            new Error(
              `Failed to read attachment: ${result.error?.message ?? "Unknown error"}`
            )
          );
          return;
        }

        const content = result.value;

        if (content.format === Office.MailboxEnums.AttachmentContentFormat.Base64) {
          resolve(content.content);
        } else {
          // For non-base64 formats, we still return the content but log a note
          resolve(content.content);
        }
      }
    );
  });
}

/**
 * Sanitizes a string to prevent XSS when displayed in the taskpane.
 * Removes HTML tags and trims whitespace. Does NOT log the content.
 */
function sanitizeString(input: string): string {
  if (!input || typeof input !== "string") {
    return "";
  }

  return input
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#x27;")
    .trim();
}
