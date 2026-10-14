import { ChangeDetectionStrategy, Component } from '@angular/core';

import { AppShellComponent } from './shared/components/app-shell/app-shell.component';

@Component({
  selector: 'app-root',
  imports: [AppShellComponent],
  template: '<app-shell />',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppComponent {
  title = 'frontend';
}
