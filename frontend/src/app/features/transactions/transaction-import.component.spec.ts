import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';

import { BankStatus, TaxWorkspaceApiService } from '../../shared/services/tax-workspace-api.service';
import { TransactionImportComponent } from './transaction-import.component';

describe('TransactionImportComponent bank connection', () => {
  const api = {
    getBankStatus: vi.fn(),
    connectBank: vi.fn(),
    syncBank: vi.fn(),
    importTransactions: vi.fn(),
  };

  async function create(status: BankStatus): Promise<ComponentFixture<TransactionImportComponent>> {
    api.getBankStatus.mockReturnValue(of(status));
    await TestBed.configureTestingModule({
      imports: [TransactionImportComponent],
      providers: [{ provide: TaxWorkspaceApiService, useValue: api }],
    }).compileComponents();

    const fixture = TestBed.createComponent(TransactionImportComponent);
    fixture.componentRef.setInput('properties', []);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    return fixture;
  }

  const text = (fixture: ComponentFixture<unknown>) => (fixture.nativeElement as HTMLElement).textContent ?? '';

  beforeEach(() => {
    vi.resetAllMocks();
    TestBed.resetTestingModule();
  });

  it('hides the bank section when the provider is not configured', async () => {
    const fixture = await create({ enabled: false, connected: false, last_synced_at: null });

    expect(text(fixture)).not.toContain('Bank connection');
  });

  it('offers to connect when enabled but not connected', async () => {
    const fixture = await create({ enabled: true, connected: false, last_synced_at: null });

    expect(text(fixture)).toContain('Connect bank');
    expect(text(fixture)).not.toContain('Sync now');
  });

  it('syncs and shows the import result', async () => {
    api.syncBank.mockReturnValue(of({ imported: 3, duplicates: 1, outside_financial_years: 0, last_synced_at: '2026-11-05T00:00:00Z' }));
    const fixture = await create({ enabled: true, connected: true, last_synced_at: null });
    const imported = vi.fn();
    fixture.componentInstance.imported.subscribe(imported);

    fixture.componentInstance.syncBank();
    fixture.detectChanges();

    expect(text(fixture)).toContain('Imported 3');
    expect(imported).toHaveBeenCalled();
  });

  it('shows an error when the provider fails', async () => {
    api.syncBank.mockReturnValue(throwError(() => new Error('502')));
    const fixture = await create({ enabled: true, connected: true, last_synced_at: null });

    fixture.componentInstance.syncBank();
    fixture.detectChanges();

    expect(text(fixture)).toContain('could not be reached');
  });
});
