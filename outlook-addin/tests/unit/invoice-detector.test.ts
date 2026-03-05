/**
 * Unit tests for the Invoice Detector service.
 *
 * Tests heuristic detection of invoice emails including subject keywords,
 * body content analysis, dollar amounts, invoice numbers, and PDF attachments.
 */

import { detectInvoice } from "../../src/services/invoice-detector";
import type { EmailData, AttachmentMeta } from "../../src/types/invoice";

/** Helper to create a minimal EmailData object for testing. */
function makeEmail(overrides: Partial<EmailData> = {}): EmailData {
  return {
    from: "vendor@example.com",
    fromName: "Vendor Corp",
    subject: "",
    bodyText: "",
    attachments: [],
    messageId: "test-msg-001",
    receivedDateTime: "2026-01-15T10:00:00Z",
    ...overrides,
  };
}

/** Helper to create a PDF attachment metadata entry. */
function makePdfAttachment(name: string = "document.pdf"): AttachmentMeta {
  return {
    id: "att-001",
    name,
    contentType: "application/pdf",
    size: 50000,
    isInline: false,
  };
}

describe("Invoice Detector", () => {
  describe("Subject keyword detection", () => {
    it("should detect invoice keyword in subject", () => {
      const email = makeEmail({ subject: "Invoice #12345 from Vendor Corp" });
      const result = detectInvoice(email);

      expect(result.confidence).toBeGreaterThan(0);
      const subjectSignal = result.signals.find((s) =>
        s.name.includes("Subject")
      );
      expect(subjectSignal?.triggered).toBe(true);
    });

    it("should detect payment keyword in subject", () => {
      const email = makeEmail({ subject: "Payment Due - Account #987" });
      const result = detectInvoice(email);

      const subjectSignal = result.signals.find((s) =>
        s.name.includes("Subject")
      );
      expect(subjectSignal?.triggered).toBe(true);
    });

    it("should detect bill keyword in subject", () => {
      const email = makeEmail({ subject: "Your monthly bill is ready" });
      const result = detectInvoice(email);

      const subjectSignal = result.signals.find((s) =>
        s.name.includes("Subject")
      );
      expect(subjectSignal?.triggered).toBe(true);
    });

    it("should not trigger on unrelated subjects", () => {
      const email = makeEmail({ subject: "Team meeting tomorrow at 3pm" });
      const result = detectInvoice(email);

      const subjectSignal = result.signals.find((s) =>
        s.name.includes("Subject")
      );
      expect(subjectSignal?.triggered).toBe(false);
    });

    it("should be case-insensitive", () => {
      const email = makeEmail({ subject: "INVOICE FROM ACME CORP" });
      const result = detectInvoice(email);

      const subjectSignal = result.signals.find((s) =>
        s.name.includes("Subject")
      );
      expect(subjectSignal?.triggered).toBe(true);
    });
  });

  describe("Body keyword detection", () => {
    it("should detect multiple invoice keywords in body", () => {
      const email = makeEmail({
        bodyText:
          "Please find the attached invoice. Total amount due is $5,000.00. Payment terms: Net 30.",
      });
      const result = detectInvoice(email);

      const bodySignal = result.signals.find((s) =>
        s.name.includes("Body")
      );
      expect(bodySignal?.triggered).toBe(true);
    });

    it("should not trigger with only one keyword", () => {
      const email = makeEmail({
        bodyText: "This is a regular email with the word invoice mentioned once.",
      });
      const result = detectInvoice(email);

      const bodySignal = result.signals.find((s) =>
        s.name.includes("Body contains")
      );
      expect(bodySignal?.triggered).toBe(false);
    });

    it("should detect billing-related terms", () => {
      const email = makeEmail({
        bodyText:
          "Bill to: John Doe\nSubtotal: $1,200.00\nTax: $96.00\nBalance due: $1,296.00",
      });
      const result = detectInvoice(email);

      const bodySignal = result.signals.find((s) =>
        s.name.includes("Body")
      );
      expect(bodySignal?.triggered).toBe(true);
    });
  });

  describe("Invoice number detection", () => {
    it("should detect INV-prefixed numbers", () => {
      const email = makeEmail({
        bodyText: "Invoice Number: INV-2026001",
      });
      const result = detectInvoice(email);

      expect(result.fields.invoiceNumber).toBeTruthy();
      const signal = result.signals.find((s) =>
        s.name.includes("Invoice number")
      );
      expect(signal?.triggered).toBe(true);
    });

    it("should detect Invoice # format", () => {
      const email = makeEmail({
        bodyText: "Invoice #78901 dated January 15, 2026",
      });
      const result = detectInvoice(email);

      expect(result.fields.invoiceNumber).toBeTruthy();
    });

    it("should detect invoice number in subject", () => {
      const email = makeEmail({
        subject: "INV-20260115 - Monthly Services",
      });
      const result = detectInvoice(email);

      expect(result.fields.invoiceNumber).toBeTruthy();
    });

    it("should not detect invoice numbers in unrelated text", () => {
      const email = makeEmail({
        bodyText: "Hi team, let's discuss the project timeline.",
      });
      const result = detectInvoice(email);

      expect(result.fields.invoiceNumber).toBeNull();
    });
  });

  describe("Dollar amount detection", () => {
    it("should detect dollar amounts", () => {
      const email = makeEmail({
        bodyText: "Total: $1,234.56",
      });
      const result = detectInvoice(email);

      expect(result.fields.totalAmount).toBe("$1,234.56");
      const signal = result.signals.find((s) =>
        s.name.includes("Dollar")
      );
      expect(signal?.triggered).toBe(true);
    });

    it("should pick the largest amount as total", () => {
      const email = makeEmail({
        bodyText:
          "Subtotal: $100.00\nTax: $8.00\nTotal: $108.00\nDeposit: $50.00",
      });
      const result = detectInvoice(email);

      expect(result.fields.totalAmount).toBe("$108.00");
    });

    it("should handle amounts without cents", () => {
      const email = makeEmail({
        bodyText: "Amount due: $5,000",
      });
      const result = detectInvoice(email);

      expect(result.fields.totalAmount).toBeTruthy();
    });

    it("should not detect non-monetary numbers", () => {
      const email = makeEmail({
        bodyText: "We have 5000 items in stock.",
      });
      const result = detectInvoice(email);

      expect(result.fields.totalAmount).toBeNull();
    });
  });

  describe("PDF attachment detection", () => {
    it("should detect PDF attachments", () => {
      const email = makeEmail({
        attachments: [makePdfAttachment("invoice.pdf")],
      });
      const result = detectInvoice(email);

      const signal = result.signals.find((s) =>
        s.name.includes("PDF attachment")
      );
      expect(signal?.triggered).toBe(true);
    });

    it("should detect invoice-named PDFs", () => {
      const email = makeEmail({
        attachments: [makePdfAttachment("Invoice_2026_001.pdf")],
      });
      const result = detectInvoice(email);

      const signal = result.signals.find((s) =>
        s.name.includes("PDF named")
      );
      expect(signal?.triggered).toBe(true);
    });

    it("should not flag non-invoice PDF names", () => {
      const email = makeEmail({
        attachments: [makePdfAttachment("meeting-notes.pdf")],
      });
      const result = detectInvoice(email);

      const pdfNameSignal = result.signals.find((s) =>
        s.name.includes("PDF named")
      );
      expect(pdfNameSignal?.triggered).toBe(false);
    });

    it("should detect PDFs by extension even with wrong content type", () => {
      const email = makeEmail({
        attachments: [
          {
            id: "att-001",
            name: "report.pdf",
            contentType: "application/octet-stream",
            size: 50000,
            isInline: false,
          },
        ],
      });
      const result = detectInvoice(email);

      const signal = result.signals.find((s) =>
        s.name.includes("PDF attachment")
      );
      expect(signal?.triggered).toBe(true);
    });
  });

  describe("Due date detection", () => {
    it("should detect due date with slash format", () => {
      const email = makeEmail({
        bodyText: "Due Date: 02/15/2026",
      });
      const result = detectInvoice(email);

      expect(result.fields.dueDate).toBe("02/15/2026");
    });

    it("should detect due date with written month", () => {
      const email = makeEmail({
        bodyText: "Due by: February 15, 2026",
      });
      const result = detectInvoice(email);

      expect(result.fields.dueDate).toBe("February 15, 2026");
    });

    it("should detect pay by date", () => {
      const email = makeEmail({
        bodyText: "Pay by: 03/01/2026",
      });
      const result = detectInvoice(email);

      expect(result.fields.dueDate).toBe("03/01/2026");
    });
  });

  describe("PO number detection", () => {
    it("should detect PO number", () => {
      const email = makeEmail({
        bodyText: "PO#: PO-2026-4567",
      });
      const result = detectInvoice(email);

      expect(result.fields.poNumber).toBeTruthy();
    });

    it("should detect Purchase Order references", () => {
      const email = makeEmail({
        bodyText: "Purchase Order: 98765",
      });
      const result = detectInvoice(email);

      expect(result.fields.poNumber).toBeTruthy();
    });
  });

  describe("Confidence scoring", () => {
    it("should return high confidence for obvious invoices", () => {
      const email = makeEmail({
        subject: "Invoice #12345",
        bodyText:
          "Invoice Number: INV-12345\nTotal amount due: $5,250.00\nDue Date: 03/15/2026\nPayment terms: Net 30\nBill to: Your Company",
        attachments: [makePdfAttachment("Invoice_12345.pdf")],
      });
      const result = detectInvoice(email);

      expect(result.isInvoice).toBe(true);
      expect(result.confidence).toBeGreaterThanOrEqual(0.7);
    });

    it("should return low confidence for non-invoice emails", () => {
      const email = makeEmail({
        subject: "Weekly team standup notes",
        bodyText:
          "Hi everyone, here are the notes from today's standup. Please review and add any items I missed.",
      });
      const result = detectInvoice(email);

      expect(result.isInvoice).toBe(false);
      expect(result.confidence).toBeLessThan(0.4);
    });

    it("should return medium confidence for ambiguous emails", () => {
      const email = makeEmail({
        subject: "Re: Project Update",
        bodyText: "The total amount for the project is $10,000. Please see the attached invoice.",
        attachments: [makePdfAttachment("project-details.pdf")],
      });
      const result = detectInvoice(email);

      expect(result.confidence).toBeGreaterThan(0.2);
    });

    it("should set vendor name from sender", () => {
      const email = makeEmail({
        fromName: "Acme Supplies Inc.",
      });
      const result = detectInvoice(email);

      expect(result.fields.vendorName).toBe("Acme Supplies Inc.");
    });
  });

  describe("HTML entity handling", () => {
    it("should handle HTML-encoded content from email reader", () => {
      const email = makeEmail({
        subject: "Invoice &amp; Receipt #12345",
        bodyText:
          "Amount: $500.00\nInvoice No: INV-9999\nDue date: 01/30/2026\nPayment terms: Net 30",
      });
      const result = detectInvoice(email);

      // Subject should still match "invoice" even with &amp;
      const subjectSignal = result.signals.find((s) =>
        s.name.includes("Subject")
      );
      expect(subjectSignal?.triggered).toBe(true);
    });
  });
});
