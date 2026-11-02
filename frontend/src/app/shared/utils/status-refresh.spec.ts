import { of, throwError } from 'rxjs';
import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  refreshEvery,
  STATUS_REFRESH_INTERVAL_MS,
} from './status-refresh';

describe('refreshEvery', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('refreshes on the configured interval', async () => {
    vi.useFakeTimers();
    const request = vi.fn(() => of('updated'));
    const onError = vi.fn();
    const onValue = vi.fn();
    const subscription = refreshEvery(request, onError).subscribe(onValue);

    await vi.advanceTimersByTimeAsync(STATUS_REFRESH_INTERVAL_MS);

    expect(request).toHaveBeenCalledTimes(1);
    expect(onValue).toHaveBeenCalledWith('updated');
    expect(onError).not.toHaveBeenCalled();

    subscription.unsubscribe();
  });

  it('reports request failures and continues polling', async () => {
    vi.useFakeTimers();
    const request = vi.fn()
      .mockReturnValueOnce(throwError(() => new Error('offline')))
      .mockReturnValueOnce(of('recovered'));
    const onError = vi.fn();
    const onValue = vi.fn();
    const subscription = refreshEvery(request, onError).subscribe(onValue);

    await vi.advanceTimersByTimeAsync(STATUS_REFRESH_INTERVAL_MS);
    await vi.advanceTimersByTimeAsync(STATUS_REFRESH_INTERVAL_MS);

    expect(onError).toHaveBeenCalledTimes(1);
    expect(onValue).toHaveBeenCalledWith('recovered');

    subscription.unsubscribe();
  });
});
