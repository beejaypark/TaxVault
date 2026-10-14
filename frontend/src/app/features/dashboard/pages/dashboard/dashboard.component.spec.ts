import { provideRouter } from '@angular/router';
import { ComponentFixture, TestBed } from '@angular/core/testing';

import { DashboardComponent } from './dashboard.component';

describe('DashboardComponent', () => {
  let fixture: ComponentFixture<DashboardComponent>;
  let component: DashboardComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        provideRouter([]),
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;

    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should display the financial year', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('FY 2025–26');
  });

  it('should display the summary counts', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('24');
    expect(element.textContent).toContain('3');
    expect(element.textContent).toContain('21');
  });

  it('should display documents needing attention', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('Bunnings');
    expect(element.textContent).toContain('$340.00');
    expect(element.textContent).toContain('Needs review');
  });

  it('should display recent documents', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('Woolworths');
    expect(element.textContent).toContain('$54.20');
    expect(element.textContent).toContain('Ready');
  });
});
