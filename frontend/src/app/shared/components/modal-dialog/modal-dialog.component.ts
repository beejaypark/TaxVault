import {
  AfterViewInit,
  Component,
  ElementRef,
  EventEmitter,
  Input,
  Output,
  ViewChild,
} from '@angular/core';

let nextModalId = 0;

@Component({
  selector: 'tv-modal-dialog',
  standalone: true,
  template: `
    <dialog
      #dialog
      class="modal-dialog"
      [attr.aria-labelledby]="titleId"
      (cancel)="onCancel($event)"
      (click)="onDialogClick($event)"
    >
      <section class="modal-panel" [class]="sizeClass">
        <header class="modal-header">
          <div>
            <h2 [id]="titleId">{{ title }}</h2>
            @if (description) {
              <p>{{ description }}</p>
            }
          </div>
          <button type="button" class="modal-close" aria-label="Close dialog" (click)="closeDialog()">
            <span aria-hidden="true">&times;</span>
          </button>
        </header>
        <div class="modal-body">
          <ng-content select="[modal-body]" />
        </div>
        <footer class="modal-footer">
          <ng-content select="[modal-actions]" />
        </footer>
      </section>
    </dialog>
  `,
  styleUrl: './modal-dialog.component.scss',
})
export class ModalDialogComponent implements AfterViewInit {
  private isOpenState = false;

  @Input('open')
  set isOpen(value: boolean) {
    this.isOpenState = value;
    this.syncOpenState();
  }

  @Input({ required: true }) title = '';
  @Input() description = '';
  @Input() size: 'small' | 'medium' | 'large' = 'medium';
  @Output() closed = new EventEmitter<void>();
  @ViewChild('dialog') private dialogRef?: ElementRef<HTMLDialogElement>;

  readonly titleId = `tv-modal-title-${nextModalId++}`;

  get sizeClass(): string {
    return `modal-panel-${this.size}`;
  }

  ngAfterViewInit(): void {
    this.syncOpenState();
  }

  onCancel(event: Event): void {
    event.preventDefault();
    this.closeDialog();
  }

  onDialogClick(event: MouseEvent): void {
    if (event.target === this.dialogRef?.nativeElement) {
      this.closeDialog();
    }
  }

  closeDialog(): void {
    const dialog = this.dialogRef?.nativeElement;
    if (dialog?.open) {
      dialog.close();
      this.closed.emit();
    }
  }

  private syncOpenState(): void {
    const dialog = this.dialogRef?.nativeElement;
    if (!dialog) {
      return;
    }

    if (this.isOpenState && !dialog.open) {
      dialog.showModal();
    } else if (!this.isOpenState && dialog.open) {
      dialog.close();
    }
  }
}
