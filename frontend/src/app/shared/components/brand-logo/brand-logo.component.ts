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
    <span class="logo-name">TAXVAULT</span>
  `,
  styles: [`
    :host { display: inline-flex; align-items: center; gap: 10px; }
    .logo-mark { flex: none; display: block; object-fit: contain; }
    .logo-name { font-family: var(--tv-font-heading); font-size: 17px; font-weight: 800; letter-spacing: 0.08em; }
  `],
})
export class BrandLogoComponent {
  @Input() size = 34;
}
