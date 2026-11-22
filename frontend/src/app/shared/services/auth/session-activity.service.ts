import { DOCUMENT } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { DestroyRef, Injectable, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { EMPTY, asyncScheduler, catchError, exhaustMap, filter, fromEvent, merge, throttleTime, timer } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class SessionActivityService {
  private readonly http = inject(HttpClient);
  private readonly document = inject(DOCUMENT);

  track(destroyRef: DestroyRef): void {
    merge(
      fromEvent(this.document, 'pointerdown', { capture: true }),
      fromEvent(this.document, 'pointermove', { capture: true, passive: true }),
      fromEvent(this.document, 'keydown', { capture: true }),
      fromEvent(this.document, 'wheel', { capture: true, passive: true }),
      fromEvent(this.document, 'input', { capture: true }),
    ).pipe(
      filter(event => event.isTrusted && this.document.visibilityState === 'visible'),
      throttleTime(10_000, asyncScheduler, { leading: true, trailing: true }),
      exhaustMap(() => this.http.post<void>('/api/auth/activity', {}).pipe(catchError(() => EMPTY))),
      takeUntilDestroyed(destroyRef),
    ).subscribe();

    merge(
      timer(0, 30_000),
      fromEvent(this.document, 'visibilitychange').pipe(filter(() => this.document.visibilityState === 'visible')),
    ).pipe(
      exhaustMap(() => this.http.get('/api/auth/user').pipe(catchError(() => EMPTY))),
      takeUntilDestroyed(destroyRef),
    ).subscribe();
  }
}
