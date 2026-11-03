import { ChangeDetectionStrategy, Component, Input } from '@angular/core';

@Component({
  selector: 'tv-brand-logo',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <svg
      class="logo-mark"
      viewBox="0 0 48 48"
      [style.width.px]="size"
      [style.height.px]="size"
      aria-hidden="true"
    >
      <circle cx="24" cy="24" r="20" fill="#3157d5" />
      <circle cx="24" cy="24" r="13" fill="none" stroke="#fff" stroke-width="3" />
      <circle cx="24" cy="24" r="3.5" fill="#fff" />
      <path d="M24 11v5M24 32v5M11 24h5M32 24h5" stroke="#fff" stroke-width="3" stroke-linecap="round" />
    </svg>
    <span class="logo-name">TAXVAULT</span>
  `,
  styles: [`
    :host { display: inline-flex; align-items: center; gap: 10px; }
    .logo-mark { flex: none; }
    .logo-name { font-size: 16px; font-weight: 750; letter-spacing: 0.05em; }
  `],
})
export class BrandLogoComponent {
  @Input() size = 34;
}
