import { TestBed } from '@angular/core/testing';

import { UserPreferencesService } from './user-preferences.service';

describe('UserPreferencesService', () => {
  beforeEach(() => {
    localStorage.removeItem('taxvault.preferences');
    TestBed.resetTestingModule();
  });

  it('uses defaults when nothing is stored', () => {
    const service = TestBed.inject(UserPreferencesService);

    expect(service.preferences()).toEqual({
      notifyDocuments: true,
      notifyUncategorised: true,
      defaultFinancialYear: 'current',
    });
  });

  it('persists updates and reloads them', () => {
    TestBed.inject(UserPreferencesService).update({ notifyDocuments: false, defaultFinancialYear: 'all' });
    TestBed.resetTestingModule();

    expect(TestBed.inject(UserPreferencesService).preferences()).toEqual({
      notifyDocuments: false,
      notifyUncategorised: true,
      defaultFinancialYear: 'all',
    });
  });

  it('falls back to defaults on corrupt storage', () => {
    localStorage.setItem('taxvault.preferences', '{not json');

    expect(TestBed.inject(UserPreferencesService).preferences().notifyDocuments).toBe(true);
  });
});
