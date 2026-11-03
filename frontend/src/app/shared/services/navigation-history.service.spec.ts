import { NavigationEnd, Router } from '@angular/router';
import { TestBed } from '@angular/core/testing';
import { Subject } from 'rxjs';

import { NavigationHistoryService } from './navigation-history.service';

describe('NavigationHistoryService', () => {
  let events: Subject<unknown>;
  let navigateByUrl: ReturnType<typeof vi.fn>;
  let navigation: NavigationHistoryService;

  beforeEach(() => {
    events = new Subject();
    navigateByUrl = vi.fn().mockResolvedValue(true);

    TestBed.configureTestingModule({
      providers: [
        {
          provide: Router,
          useValue: {
            url: '/dashboard',
            events,
            navigateByUrl,
          },
        },
      ],
    });

    navigation = TestBed.inject(NavigationHistoryService);
  });

  it('returns to the previous in-app route and labels the destination', () => {
    events.next(new NavigationEnd(1, '/dashboard', '/dashboard'));
    events.next(new NavigationEnd(2, '/reviews', '/reviews'));

    expect(navigation.isVisible).toBe(true);
    expect(navigation.backLabel).toBe('Back to Dashboard');

    navigation.goBack();

    expect(navigateByUrl).toHaveBeenCalledWith('/dashboard');
  });

  it('does not show a back button when opening a deep link directly', () => {
    navigation = TestBed.runInInjectionContext(() => {
      const router = TestBed.inject(Router);
      Object.defineProperty(router, 'url', { value: '/reviews/review-1' });
      return new NavigationHistoryService();
    });

    expect(navigation.isVisible).toBe(false);
  });

  it('does not show a back button after primary navigation', () => {
    events.next(new NavigationEnd(1, '/dashboard', '/dashboard'));
    navigation.clearForPrimaryNavigation('/reviews');
    events.next(new NavigationEnd(2, '/reviews', '/reviews'));

    expect(navigation.isVisible).toBe(false);

    events.next(new NavigationEnd(3, '/reviews/review-1', '/reviews/review-1'));

    expect(navigation.isVisible).toBe(true);
    expect(navigation.backLabel).toBe('Back to Reviews');
  });
});
