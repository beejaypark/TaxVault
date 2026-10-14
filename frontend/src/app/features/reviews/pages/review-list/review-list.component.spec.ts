import {
  ComponentFixture,
  TestBed,
} from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';

import { Review } from '../../../../shared/models/reviews/review.model';
import {
  ReviewListResponse,
} from '../../../../shared/services/reviews/review.service';
import {
  ReviewService,
} from '../../../../shared/services/reviews/review.service';

import { ReviewListComponent } from './review-list.component';

describe('ReviewListComponent', () => {
  let fixture: ComponentFixture<ReviewListComponent>;
  let component: ReviewListComponent;

  const reviews: Review[] = [
    {
      id: 'review-1',
      userId: 'user-1',
      type: 'confidence',
      status: 'pending',
      target: {
        documentId: 'document-1',
        documentExtractionId: 'extraction-1',
      },
      decision: null,
      reviewerId: 'user-1',
      notes: 'Low confidence field',
      createdAt: '2026-09-01T00:00:00Z',
      completedAt: null,
      supersedesReviewId: null,
    },
    {
      id: 'review-2',
      userId: 'user-1',
      type: 'deductibility',
      status: 'completed',
      target: {
        documentId: 'document-2',
      },
      decision: 'approved',
      reviewerId: 'user-1',
      notes: null,
      createdAt: '2026-09-02T00:00:00Z',
      completedAt: '2026-09-03T00:00:00Z',
      supersedesReviewId: null,
    },
  ];

  const response: ReviewListResponse = {
    data: reviews,
    currentPage: 1,
    lastPage: 1,
    total: 2,
  };

  const reviewService = {
    listReviews: vi.fn(() => of(response)),
  };

  beforeEach(async () => {
    vi.clearAllMocks();
    reviewService.listReviews.mockReturnValue(
      of(response),
    );

    await TestBed.configureTestingModule({
      imports: [ReviewListComponent],
      providers: [
        provideRouter([]),
        {
          provide: ReviewService,
          useValue: reviewService,
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(
      ReviewListComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should load reviews', () => {
    expect(
      reviewService.listReviews,
    ).toHaveBeenCalled();

    expect(component.reviews).toEqual(reviews);
  });

  it('should render review type and status', () => {
    const element =
      fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain(
      'confidence',
    );

    expect(element.textContent).toContain(
      'pending',
    );

    expect(element.textContent).toContain(
      'deductibility',
    );

    expect(element.textContent).toContain(
      'completed',
    );
  });

  it('should render an empty state when no reviews exist', () => {
    reviewService.listReviews.mockReturnValue(
      of({
        data: [],
        currentPage: 1,
        lastPage: 1,
        total: 0,
      }),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewListComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    const element =
      fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain(
      'No reviews found.',
    );
  });

  it('should render document identifiers', () => {
    const element =
      fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain(
      'document-1',
    );

    expect(element.textContent).toContain(
      'document-2',
    );
  });
  it('should render a detail link for each review', () => {
    const element =
      fixture.nativeElement as HTMLElement;

    const links =
      element.querySelectorAll(
        '[data-testid="review-detail-link"]',
      );

    expect(links.length).toBe(2);

    expect(
      (links[0] as HTMLAnchorElement).getAttribute(
        'href',
      ),
    ).toBe('/reviews/review-1');

    expect(
      (links[1] as HTMLAnchorElement).getAttribute(
        'href',
      ),
    ).toBe('/reviews/review-2');
  });
  it('should render status and type filter controls', () => {
    const element =
      fixture.nativeElement as HTMLElement;

    expect(
      element.querySelector(
        '[data-testid="review-status-filter"]',
      ),
    ).toBeTruthy();

    expect(
      element.querySelector(
        '[data-testid="review-type-filter"]',
      ),
    ).toBeTruthy();
  });

  it('should reload reviews when status filter changes', () => {
    const element =
      fixture.nativeElement as HTMLElement;

    const select =
      element.querySelector(
        '[data-testid="review-status-filter"]',
      ) as HTMLSelectElement;

    select.value = 'pending';
    select.dispatchEvent(new Event('change'));

    fixture.detectChanges();

    expect(
      reviewService.listReviews,
    ).toHaveBeenLastCalledWith({
      status: 'pending',
    });
  });

  it('should reload reviews when type filter changes', () => {
    const element =
      fixture.nativeElement as HTMLElement;

    const select =
      element.querySelector(
        '[data-testid="review-type-filter"]',
      ) as HTMLSelectElement;

    select.value = 'deductibility';
    select.dispatchEvent(new Event('change'));

    fixture.detectChanges();

    expect(
      reviewService.listReviews,
    ).toHaveBeenLastCalledWith({
      type: 'deductibility',
    });
  });

  it('should render pagination controls when multiple pages exist', () => {
    reviewService.listReviews.mockReturnValue(
      of({
        data: reviews,
        currentPage: 1,
        lastPage: 3,
        total: 6,
      }),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewListComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    const element =
      fixture.nativeElement as HTMLElement;

    expect(
      element.querySelector(
        '[data-testid="review-pagination"]',
      ),
    ).toBeTruthy();

    expect(
      element.querySelector(
        '[data-testid="review-page-indicator"]',
      )?.textContent,
    ).toContain('Page 1 of 3');

    expect(
      element.querySelector(
        '[data-testid="review-previous-page"]',
      ),
    ).toBeTruthy();

    expect(
      element.querySelector(
        '[data-testid="review-next-page"]',
      ),
    ).toBeTruthy();
  });

  it('should disable previous page on the first page', () => {
    reviewService.listReviews.mockReturnValue(
      of({
        data: reviews,
        currentPage: 1,
        lastPage: 3,
        total: 6,
      }),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewListComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    const element =
      fixture.nativeElement as HTMLElement;

    const previousButton =
      element.querySelector(
        '[data-testid="review-previous-page"]',
      ) as HTMLButtonElement;

    expect(previousButton.disabled).toBe(true);
  });

  it('should disable next page on the last page', () => {
    reviewService.listReviews.mockReturnValue(
      of({
        data: reviews,
        currentPage: 3,
        lastPage: 3,
        total: 6,
      }),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewListComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    const element =
      fixture.nativeElement as HTMLElement;

    const nextButton =
      element.querySelector(
        '[data-testid="review-next-page"]',
      ) as HTMLButtonElement;

    expect(nextButton.disabled).toBe(true);
  });

  it('should load the next page while retaining active filters', () => {
    reviewService.listReviews.mockReturnValue(
      of({
        data: reviews,
        currentPage: 1,
        lastPage: 3,
        total: 6,
      }),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewListComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    reviewService.listReviews.mockClear();

    component.selectedStatus = 'pending';
    component.selectedType = 'confidence';
    component.currentPage = 1;
    component.lastPage = 3;

    fixture.detectChanges();

    const element =
      fixture.nativeElement as HTMLElement;

    const nextButton =
      element.querySelector(
        '[data-testid="review-next-page"]',
      ) as HTMLButtonElement;

    nextButton.click();

    fixture.detectChanges();

    expect(
      reviewService.listReviews,
    ).toHaveBeenLastCalledWith({
      status: 'pending',
      type: 'confidence',
      page: 2,
    });
  });

  it('should not navigate beyond the last page', () => {
    reviewService.listReviews.mockReturnValue(
      of({
        data: reviews,
        currentPage: 3,
        lastPage: 3,
        total: 6,
      }),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewListComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    reviewService.listReviews.mockClear();

    const element =
      fixture.nativeElement as HTMLElement;

    const nextButton =
      element.querySelector(
        '[data-testid="review-next-page"]',
      ) as HTMLButtonElement;

    nextButton.click();

    fixture.detectChanges();

    expect(
      reviewService.listReviews,
    ).not.toHaveBeenCalled();
  });
  it('should reload reviews with both status and type filters', () => {
    const element =
      fixture.nativeElement as HTMLElement;

    const statusSelect =
      element.querySelector(
        '[data-testid="review-status-filter"]',
      ) as HTMLSelectElement;

    const typeSelect =
      element.querySelector(
        '[data-testid="review-type-filter"]',
      ) as HTMLSelectElement;

    statusSelect.value = 'completed';
    statusSelect.dispatchEvent(new Event('change'));

    typeSelect.value = 'confidence';
    typeSelect.dispatchEvent(new Event('change'));

    fixture.detectChanges();

    expect(
      reviewService.listReviews,
    ).toHaveBeenLastCalledWith({
      status: 'completed',
      type: 'confidence',
    });
  });
});
  it('should define review list and detail routes', async () => {
    const { routes } = await import('../../../../app.routes');

    expect(
      routes.some(
        (route) =>
          route.path === 'reviews' &&
          route.component === ReviewListComponent,
      ),
    ).toBe(true);

    expect(
      routes.some(
        (route) =>
          route.path === 'reviews/:id',
      ),
    ).toBe(true);
  });


