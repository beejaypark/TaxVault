import { EMPTY, Observable, catchError, exhaustMap, interval } from 'rxjs';

export const STATUS_REFRESH_INTERVAL_MS = 5000;

export function refreshEvery<T>(
  request: () => Observable<T>,
  onError: (error: unknown) => void,
): Observable<T> {
  return interval(STATUS_REFRESH_INTERVAL_MS).pipe(
    exhaustMap(() => request().pipe(
      catchError((error: unknown) => {
        onError(error);
        return EMPTY;
      }),
    )),
  );
}
