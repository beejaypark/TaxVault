import { ChangeDetectionStrategy, Component, inject } from '@angular/core';

import { TaxIconComponent } from '../tax-icon/tax-icon.component';
import { NavigationHistoryService } from '../../services/navigation-history.service';

@Component({
  selector: 'tv-back-navigation',
  standalone: true,
  imports: [TaxIconComponent],
  template: `
    @if (navigation.isVisible) {
      <button type="button" class="back-button" (click)="navigation.goBack()">
        <tv-icon name="arrowLeft" />
        {{ navigation.backLabel }}
      </button>
    }
  `,
  styles: [`
    :host {
      display: block;
      margin-bottom: 16px;
    }

    .back-button {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 0;
      border: 0;
      background: transparent;
      color: var(--tv-color-text-secondary);
      font: inherit;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
    }

    .back-button:hover {
      color: var(--tv-color-primary);
    }

    .back-button:focus-visible {
      outline: 2px solid var(--tv-color-primary);
      outline-offset: 4px;
      border-radius: 2px;
    }
  `],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackNavigationComponent {
  readonly navigation = inject(NavigationHistoryService);
}
