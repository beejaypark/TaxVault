import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ModalDialogComponent } from './modal-dialog.component';

@Component({
  imports: [ModalDialogComponent],
  template: `
    <tv-modal-dialog [open]="isOpen()" title="Confirm action" (closed)="isOpen.set(false)">
      <p modal-body>Dialog content</p>
      <button modal-actions type="button">Confirm</button>
    </tv-modal-dialog>
  `,
})
class TestHostComponent {
  isOpen = signal(false);
}

describe('ModalDialogComponent', () => {
  let fixture: ComponentFixture<TestHostComponent>;
  let showModal: ReturnType<typeof vi.fn>;
  let close: ReturnType<typeof vi.fn>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TestHostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TestHostComponent);
    fixture.detectChanges();

    // The test DOM does not implement the native dialog methods.
    const dialog = fixture.nativeElement.querySelector('dialog') as HTMLDialogElement;
    showModal = vi.fn(() => dialog.setAttribute('open', ''));
    close = vi.fn(() => dialog.removeAttribute('open'));
    Object.defineProperty(dialog, 'showModal', { configurable: true, value: showModal });
    Object.defineProperty(dialog, 'close', { configurable: true, value: close });
  });

  it('opens as a native modal when requested', () => {
    fixture.componentInstance.isOpen.set(true);
    fixture.detectChanges();

    const dialog = fixture.nativeElement.querySelector('dialog') as HTMLDialogElement;
    expect(showModal).toHaveBeenCalledOnce();
    expect(dialog.open).toBe(true);
    expect(dialog.getAttribute('aria-labelledby')).toBeTruthy();
  });

  it('emits closed when the close button is activated', () => {
    fixture.componentInstance.isOpen.set(true);
    fixture.detectChanges();
    fixture.nativeElement.querySelector('.modal-close').click();
    fixture.detectChanges();

    expect(close).toHaveBeenCalledOnce();
    expect(fixture.componentInstance.isOpen()).toBe(false);
  });

  it('projects content into the body and actions regions', () => {
    expect(fixture.nativeElement.querySelector('.modal-body').textContent).toContain('Dialog content');
    expect(fixture.nativeElement.querySelector('.modal-footer').textContent).toContain('Confirm');
  });
});
