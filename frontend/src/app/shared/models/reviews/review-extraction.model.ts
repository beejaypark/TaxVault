export interface ReviewDocument {
  id: string;
  originalFilename: string;
  mimeType: string | null;
  sizeBytes: number | null;
  uploadedAt: string | null;
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
