import { ChangeDetectionStrategy, Component, HostListener } from '@angular/core';
import { RouterLink } from '@angular/router';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';

@Component({
  selector: 'app-help',
  imports: [RouterLink, TaxIconComponent],
  templateUrl: './help.component.html',
  styleUrl: './help.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HelpComponent {
  activeSection = 'workflow';

  scrollTo(sectionId: string, event: Event): void {
    event.preventDefault();
    this.activeSection = sectionId;
    document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  @HostListener('window:scroll')
  updateActiveSection(): void {
    const sections = ['workflow', 'financial-years', 'documents', 'reviews', 'money', 'reports', 'settings', 'send'];
    const marker = window.scrollY + 150;
    let current = sections[0];
    for (const sectionId of sections) {
      const section = document.getElementById(sectionId);
      if (section && section.offsetTop <= marker) current = sectionId;
    }
    this.activeSection = current;
  }
}
