import { ChangeDetectionStrategy, ChangeDetectorRef, Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { catchError, interval, of, startWith, switchMap } from 'rxjs';

import { Announcement, AnnouncementService } from '../../services/announcement.service';
import { AnnouncementBannerItemComponent } from '../announcement-banner-item/announcement-banner-item.component';

@Component({
  selector: 'app-announcement-strip',
  imports: [AnnouncementBannerItemComponent],
  template: `
    @for (announcement of announcements; track announcement.id + announcement.updated_at) {
      <app-announcement-banner-item [announcement]="announcement" (dismissed)="dismiss(announcement)" />
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AnnouncementStripComponent {
  private readonly api = inject(AnnouncementService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly changeDetector = inject(ChangeDetectorRef);
  announcements: Announcement[] = [];
  private dismissedKeys = this.readDismissed();

  constructor() {
    interval(30_000).pipe(
      startWith(0),
      switchMap(() => this.api.active().pipe(catchError(() => of({ data: [] as Announcement[] })))),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe(response => {
      this.announcements = response.data.filter(item => !this.dismissedKeys.has(this.dismissKey(item)));
      this.changeDetector.markForCheck();
    });
  }

  dismiss(announcement: Announcement): void {
    this.dismissedKeys.add(this.dismissKey(announcement));
    try {
      sessionStorage.setItem('taxvault.dismissed-announcements', JSON.stringify([...this.dismissedKeys]));
    } catch {
      // Dismissing still works for the current page if storage is unavailable.
    }
    this.announcements = this.announcements.filter(item => item.id !== announcement.id);
  }

  private dismissKey(announcement: Announcement): string {
    return `${announcement.id}:${announcement.updated_at}`;
  }

  private readDismissed(): Set<string> {
    try {
      const saved = sessionStorage.getItem('taxvault.dismissed-announcements');
      const parsed: unknown = saved ? JSON.parse(saved) : [];
      return new Set(Array.isArray(parsed) ? parsed.filter((value): value is string => typeof value === 'string') : []);
    } catch {
      return new Set();
    }
  }
}
