import { ChangeDetectionStrategy, Component, Input } from '@angular/core';

@Component({
  selector: 'tv-brand-logo',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <img
      class="logo-mark"
      src="assets/branding/taxvault-compass.svg"
      [style.width.px]="size"
      [style.height.px]="size"
      alt=""
      aria-hidden="true"
    />
    <span class="logo-name" role="img" aria-label="TaxVault"></span>
  `,
  styles: [`
    :host { display: inline-flex; align-items: center; gap: 10px; }
    .logo-mark { flex: none; display: block; object-fit: contain; }
    .logo-name {
      flex: none;
      width: 108px;
      aspect-ratio: 118 / 26;
      background-color: currentColor;
      mask: url('/assets/branding/taxvault-wordmark.svg') center / contain no-repeat;
    }
    @media (forced-colors: active) {
      .logo-name { forced-color-adjust: none; background-color: CanvasText; }
    }
  `],
})
export class BrandLogoComponent {
  @Input() size = 34;
}
