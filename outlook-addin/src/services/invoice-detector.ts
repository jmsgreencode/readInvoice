/**
 * Invoice Detector Service
 *
 * Heuristic-based detection of invoice emails. Checks subject lines,
 * body content, attachment names, and patterns for dollar amounts,
 * invoice numbers, and billing keywords.
 */

import {
  EmailData,
  InvoiceDetection,
  InvoiceFields,
  DetectionSignal,
} from "../types/invoice";

/** Keywords that indicate an invoice in the subject line. */
const SUBJECT_KEYWORDS: string[] = [
  "invoice",
  "inv#",
  "inv #",
  "payment",
  "bill",
  "billing",
  "statement",
  "receipt",
  "purchase order",
  "remittance",
  "amount due",
  "past due",
  "overdue",
  "payment due",
  "account payable",
];

/** Keywords that indicate an invoice in the body text. */
const BODY_KEYWORDS: string[] = [
  "invoice",
  "total amount",
  "amount due",
  "payment terms",
  "net 30",
  "net 60",
  "net 90",
  "due date",
  "bill to",
  "remit to",
  "remit payment",
  "tax id",
  "subtotal",
  "balance due",
  "please pay",
  "bank transfer",
  "wire transfer",
  "ach payment",
  "routing number",
  "account number",
];

/** Regex patterns for common invoice number formats. */
const INVOICE_NUMBER_PATTERNS: RegExp[] = [
  /\b(?:INV|INVOICE)[#\-\s]*(\d{3,12})\b/i,
  /\b(?:Invoice\s*(?:No|Number|#|Num)\.?\s*:?\s*)([A-Z0-9\-]{3,20})\b/i,
  /\b([A-Z]{2,4}[-]\d{4,10})\b/,
  /\b(?:Ref|Reference)[#\-\s.:]*([A-Z0-9\-]{4,15})\b/i,
];

/** Regex for dollar amounts (USD). */
const DOLLAR_AMOUNT_PATTERN =
  /\$\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)\b/g;

/** Regex for generic currency amounts. */
const CURRENCY_AMOUNT_PATTERN =
  /(?:USD|EUR|GBP|CAD|AUD)\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)\b/gi;

/** Regex for due dates. */
const DUE_DATE_PATTERNS: RegExp[] = [
  /\b(?:due\s*(?:date|by|on)?)\s*:?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/i,
  /\b(?:due\s*(?:date|by|on)?)\s*:?\s*([A-Z][a-z]+\s+\d{1,2},?\s*\d{4})\b/i,
  /\b(?:pay\s*by)\s*:?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/i,
];

/** Regex for PO numbers. */
const PO_NUMBER_PATTERNS: RegExp[] = [
  /\b(?:PO|P\.O\.|Purchase\s*Order)[#\-\s.:]*([A-Z0-9\-]{3,15})\b/i,
];

/** PDF-related content types. */
const PDF_CONTENT_TYPES: string[] = [
  "application/pdf",
  "application/x-pdf",
];

/**
 * Signal weights for computing confidence.
 */
const SIGNAL_WEIGHTS = {
  subjectKeyword: 0.25,
  bodyKeyword: 0.15,
  invoiceNumber: 0.20,
  dollarAmount: 0.15,
  pdfAttachment: 0.10,
  dueDateFound: 0.05,
  poNumber: 0.05,
  pdfNamedInvoice: 0.05,
} as const;

/**
 * Analyzes an email and returns invoice detection results.
 */
export function detectInvoice(email: EmailData): InvoiceDetection {
  const signals: DetectionSignal[] = [];
  const fields: InvoiceFields = {
    invoiceNumber: null,
    totalAmount: null,
    currency: null,
    dueDate: null,
    vendorName: null,
    poNumber: null,
  };

  // Decode HTML entities for pattern matching (email-reader encodes them)
  const subject = decodeEntities(email.subject).toLowerCase();
  const body = decodeEntities(email.bodyText).toLowerCase();
  const bodyRaw = decodeEntities(email.bodyText);

  // 1. Check subject for invoice keywords
  const subjectMatch = SUBJECT_KEYWORDS.find((kw) => subject.includes(kw));
  signals.push({
    name: "Subject contains invoice keyword",
    triggered: !!subjectMatch,
    weight: SIGNAL_WEIGHTS.subjectKeyword,
    matchedValue: subjectMatch ?? undefined,
  });

  // 2. Check body for invoice keywords
  const bodyKeywordMatches = BODY_KEYWORDS.filter((kw) => body.includes(kw));
  const bodyKeywordTriggered = bodyKeywordMatches.length >= 2;
  signals.push({
    name: "Body contains invoice keywords",
    triggered: bodyKeywordTriggered,
    weight: SIGNAL_WEIGHTS.bodyKeyword,
    matchedValue: bodyKeywordTriggered
      ? bodyKeywordMatches.slice(0, 3).join(", ")
      : undefined,
  });

  // 3. Check for invoice number patterns
  let invoiceNumber: string | null = null;
  for (const pattern of INVOICE_NUMBER_PATTERNS) {
    const match = bodyRaw.match(pattern) ?? decodeEntities(email.subject).match(pattern);
    if (match) {
      invoiceNumber = match[1] ?? match[0];
      break;
    }
  }
  fields.invoiceNumber = invoiceNumber;
  signals.push({
    name: "Invoice number detected",
    triggered: !!invoiceNumber,
    weight: SIGNAL_WEIGHTS.invoiceNumber,
    matchedValue: invoiceNumber ?? undefined,
  });

  // 4. Check for dollar/currency amounts
  const dollarMatches = bodyRaw.match(DOLLAR_AMOUNT_PATTERN);
  const currencyMatches = bodyRaw.match(CURRENCY_AMOUNT_PATTERN);
  const allAmounts = [
    ...(dollarMatches ?? []),
    ...(currencyMatches ?? []),
  ];
  const hasAmount = allAmounts.length > 0;

  if (hasAmount) {
    // Pick the largest amount as the likely total
    const amounts = allAmounts.map((a) => {
      const numStr = a.replace(/[^0-9.,]/g, "").replace(/,/g, "");
      return { original: a, value: parseFloat(numStr) || 0 };
    });
    amounts.sort((a, b) => b.value - a.value);
    fields.totalAmount = amounts[0].original.trim();
    fields.currency = fields.totalAmount.startsWith("$") ? "USD" : extractCurrencyCode(fields.totalAmount);
  }

  signals.push({
    name: "Dollar/currency amount found",
    triggered: hasAmount,
    weight: SIGNAL_WEIGHTS.dollarAmount,
    matchedValue: fields.totalAmount ?? undefined,
  });

  // 5. Check for PDF attachments
  const pdfAttachments = email.attachments.filter(
    (att) =>
      PDF_CONTENT_TYPES.includes(att.contentType.toLowerCase()) ||
      att.name.toLowerCase().endsWith(".pdf")
  );
  const hasPdf = pdfAttachments.length > 0;
  signals.push({
    name: "PDF attachment present",
    triggered: hasPdf,
    weight: SIGNAL_WEIGHTS.pdfAttachment,
    matchedValue: hasPdf
      ? pdfAttachments.map((a) => a.name).join(", ")
      : undefined,
  });

  // 6. Check if PDF is named like an invoice
  const invoicePdf = pdfAttachments.find((att) =>
    /invoice|inv|bill|receipt|statement/i.test(att.name)
  );
  signals.push({
    name: "PDF named like invoice",
    triggered: !!invoicePdf,
    weight: SIGNAL_WEIGHTS.pdfNamedInvoice,
    matchedValue: invoicePdf?.name ?? undefined,
  });

  // 7. Check for due date
  let dueDate: string | null = null;
  for (const pattern of DUE_DATE_PATTERNS) {
    const match = bodyRaw.match(pattern);
    if (match) {
      dueDate = match[1];
      break;
    }
  }
  fields.dueDate = dueDate;
  signals.push({
    name: "Due date found",
    triggered: !!dueDate,
    weight: SIGNAL_WEIGHTS.dueDateFound,
    matchedValue: dueDate ?? undefined,
  });

  // 8. Check for PO number
  let poNumber: string | null = null;
  for (const pattern of PO_NUMBER_PATTERNS) {
    const match = bodyRaw.match(pattern);
    if (match) {
      poNumber = match[1];
      break;
    }
  }
  fields.poNumber = poNumber;
  signals.push({
    name: "PO number found",
    triggered: !!poNumber,
    weight: SIGNAL_WEIGHTS.poNumber,
    matchedValue: poNumber ?? undefined,
  });

  // Set vendor name from sender
  fields.vendorName = email.fromName || email.from || null;

  // Calculate confidence score
  const confidence = calculateConfidence(signals);
  const isInvoice = confidence >= 0.4;

  return {
    isInvoice,
    confidence,
    fields,
    signals,
  };
}

/**
 * Calculates a confidence score (0-1) from triggered signals.
 */
function calculateConfidence(signals: DetectionSignal[]): number {
  let score = 0;
  let maxScore = 0;

  for (const signal of signals) {
    maxScore += signal.weight;
    if (signal.triggered) {
      score += signal.weight;
    }
  }

  if (maxScore === 0) {
    return 0;
  }

  // Normalize to 0-1 range
  return Math.min(1, Math.max(0, score / maxScore));
}

/**
 * Extracts a currency code from an amount string.
 */
function extractCurrencyCode(amount: string): string | null {
  const match = amount.match(/^(USD|EUR|GBP|CAD|AUD)/i);
  return match ? match[1].toUpperCase() : null;
}

/**
 * Decodes HTML entities that were encoded by the email reader sanitizer.
 */
function decodeEntities(text: string): string {
  return text
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#x27;/g, "'");
}
