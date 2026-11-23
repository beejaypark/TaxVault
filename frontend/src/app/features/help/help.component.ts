import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';

@Component({
  selector: 'app-help',
  imports: [RouterLink, TaxIconComponent],
  templateUrl: './help.component.html',
  styleUrl: './help.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HelpComponent {}
