/**
 * Unit tests for the Attachment Handler service.
 *
 * Tests PDF validation logic including magic bytes checking,
 * size validation, and MIME type detection.
 */

import {
  isPdfAttachment,
  isValidPdfBase64,
  isValidPdfBytes,
  isAcceptableSize,
} from "../../src/services/attachment-handler";
import type { AttachmentMeta } from "../../src/types/invoice";

/** Helper to create an AttachmentMeta object. */
function makeAttachment(overrides: Partial<AttachmentMeta> = {}): AttachmentMeta {
  return {
    id: "att-001",
    name: "document.pdf",
    contentType: "application/pdf",
    size: 50000,
    isInline: false,
    ...overrides,
  };
}

/**
 * Creates a base64 string that starts with PDF magic bytes (%PDF-).
 * "%PDF-1.7" in base64 is "JVBERi0xLjc="
 */
function createValidPdfBase64(): string {
  return "JVBERi0xLjcKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwo+Pg==";
}

/**
 * Creates a base64 string that does NOT start with PDF magic bytes.
 */
function createInvalidPdfBase64(): string {
  // This is "Hello World" in base64
  return "SGVsbG8gV29ybGQ=";
}

describe("Attachment Handler", () => {
  describe("isPdfAttachment", () => {
    it("should return true for application/pdf content type", () => {
      const att = makeAttachment({ contentType: "application/pdf" });
      expect(isPdfAttachment(att)).toBe(true);
    });

    it("should return true for application/x-pdf content type", () => {
      const att = makeAttachment({ contentType: "application/x-pdf" });
      expect(isPdfAttachment(att)).toBe(true);
    });

    it("should return true for .pdf file extension", () => {
      const att = makeAttachment({
        name: "invoice.pdf",
        contentType: "application/octet-stream",
      });
      expect(isPdfAttachment(att)).toBe(true);
    });

    it("should return true for .PDF uppercase extension", () => {
      const att = makeAttachment({
        name: "INVOICE.PDF",
        contentType: "application/octet-stream",
      });
      expect(isPdfAttachment(att)).toBe(true);
    });

    it("should return false for non-PDF files", () => {
      const att = makeAttachment({
        name: "photo.jpg",
        contentType: "image/jpeg",
      });
      expect(isPdfAttachment(att)).toBe(false);
    });

    it("should return false for Word documents", () => {
      const att = makeAttachment({
        name: "document.docx",
        contentType:
          "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      });
      expect(isPdfAttachment(att)).toBe(false);
    });

    it("should return false for Excel files", () => {
      const att = makeAttachment({
        name: "spreadsheet.xlsx",
        contentType:
          "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      });
      expect(isPdfAttachment(att)).toBe(false);
    });

    it("should handle files with pdf in the name but not as extension", () => {
      const att = makeAttachment({
        name: "pdf-guidelines.docx",
        contentType:
          "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      });
      expect(isPdfAttachment(att)).toBe(false);
    });
  });

  describe("isValidPdfBase64", () => {
    it("should return true for valid PDF base64 content", () => {
      const base64 = createValidPdfBase64();
      expect(isValidPdfBase64(base64)).toBe(true);
    });

    it("should return false for non-PDF base64 content", () => {
      const base64 = createInvalidPdfBase64();
      expect(isValidPdfBase64(base64)).toBe(false);
    });

    it("should return false for empty string", () => {
      expect(isValidPdfBase64("")).toBe(false);
    });

    it("should return false for null/undefined input", () => {
      expect(isValidPdfBase64(null as unknown as string)).toBe(false);
      expect(isValidPdfBase64(undefined as unknown as string)).toBe(false);
    });

    it("should handle base64 with whitespace", () => {
      const base64 = "  \n" + createValidPdfBase64();
      expect(isValidPdfBase64(base64)).toBe(true);
    });

    it("should return false for random text", () => {
      expect(isValidPdfBase64("This is not base64")).toBe(false);
    });

    it("should return false for JPEG magic bytes in base64", () => {
      // JPEG starts with FFD8FF, which in base64 is "/9j/"
      expect(isValidPdfBase64("/9j/4AAQSkZJRgABAQAAAQABAAD")).toBe(false);
    });

    it("should return false for PNG magic bytes in base64", () => {
      // PNG starts with 89504E47, which in base64 is "iVBOR"
      expect(isValidPdfBase64("iVBORw0KGgoAAAANSUhEUg==")).toBe(false);
    });
  });

  describe("isValidPdfBytes", () => {
    it("should return true for valid PDF bytes", () => {
      // %PDF- in ASCII: 0x25, 0x50, 0x44, 0x46, 0x2D
      const bytes = new Uint8Array([0x25, 0x50, 0x44, 0x46, 0x2d, 0x31, 0x2e, 0x37]);
      expect(isValidPdfBytes(bytes)).toBe(true);
    });

    it("should return false for non-PDF bytes", () => {
      const bytes = new Uint8Array([0x48, 0x65, 0x6c, 0x6c, 0x6f]);
      expect(isValidPdfBytes(bytes)).toBe(false);
    });

    it("should return false for empty array", () => {
      const bytes = new Uint8Array([]);
      expect(isValidPdfBytes(bytes)).toBe(false);
    });

    it("should return false for array shorter than 5 bytes", () => {
      const bytes = new Uint8Array([0x25, 0x50, 0x44, 0x46]);
      expect(isValidPdfBytes(bytes)).toBe(false);
    });

    it("should return false for null input", () => {
      expect(isValidPdfBytes(null as unknown as Uint8Array)).toBe(false);
    });

    it("should return false for JPEG bytes", () => {
      const bytes = new Uint8Array([0xff, 0xd8, 0xff, 0xe0, 0x00]);
      expect(isValidPdfBytes(bytes)).toBe(false);
    });

    it("should return false for ZIP/DOCX bytes", () => {
      // PK header
      const bytes = new Uint8Array([0x50, 0x4b, 0x03, 0x04, 0x14]);
      expect(isValidPdfBytes(bytes)).toBe(false);
    });
  });

  describe("isAcceptableSize", () => {
    it("should accept normal-sized files", () => {
      expect(isAcceptableSize(50000)).toBe(true);
    });

    it("should accept files up to 25 MB", () => {
      expect(isAcceptableSize(25 * 1024 * 1024)).toBe(true);
    });

    it("should reject files over 25 MB", () => {
      expect(isAcceptableSize(25 * 1024 * 1024 + 1)).toBe(false);
    });

    it("should reject zero-size files", () => {
      expect(isAcceptableSize(0)).toBe(false);
    });

    it("should reject negative sizes", () => {
      expect(isAcceptableSize(-1)).toBe(false);
    });

    it("should accept a 1-byte file", () => {
      expect(isAcceptableSize(1)).toBe(true);
    });

    it("should accept a 1 MB file", () => {
      expect(isAcceptableSize(1024 * 1024)).toBe(true);
    });
  });
});
