export interface ReviewDocument {
  id: string;
  financialYearId?: string | null;
  originalFilename: string;
  description?: string | null;
  mimeType: string | null;
  sizeBytes: number | null;
  uploadedAt: string | null;
  transactions?: ReviewDocumentTransaction[];
}

export interface ReviewDocumentTransaction {
  id: string;
  financialYearId: string;
  transactionDate: string;
  description: string;
  direction: 'income' | 'expense';
  amount: string | number;
  currency: string;
  atoCategory?: string | null;
  propertyId?: string | null;
}

export interface ReviewExtractionField {
  value: unknown;
  confidence: number;
  source: string;
}

export interface ReviewExtraction {
  id: string;
  provider: string;
  model: string | null;
  modelVersion: string | null;
  extractionVersion: string;
  status: string;

  schemaVersion: string | null;

  fields: Record<string, ReviewExtractionField>;

  startedAt: string | null;
  completedAt: string | null;
}
