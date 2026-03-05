/**
 * Commands Module
 *
 * Handles ribbon button actions for the proCom Outlook Add-in.
 * Registered as function commands in the manifest.
 */

/* global Office */

Office.onReady(() => {
  // Commands are ready
});

/**
 * Ribbon button handler: opens the taskpane for invoice scanning.
 * This function is referenced in manifest.xml as "scanEmail".
 *
 * @param event - The Office event from the ribbon button click.
 */
function scanEmail(event: Office.AddinCommands.Event): void {
  const message: Office.NotificationMessageDetails = {
    type: Office.MailboxEnums.ItemNotificationMessageType.InformationalMessage,
    message: "Scanning email for invoices...",
    icon: "Icon.16x16",
    persistent: false,
  };

  // Show a notification on the mail item
  const item = Office.context.mailbox?.item;
  if (item) {
    item.notificationMessages.replaceAsync("scanNotification", message, () => {
      // Notification shown; the taskpane will handle the actual scanning
      event.completed();
    });
  } else {
    event.completed();
  }
}

// Register the function with the Office runtime
// eslint-disable-next-line @typescript-eslint/no-explicit-any
(globalThis as any).scanEmail = scanEmail;
