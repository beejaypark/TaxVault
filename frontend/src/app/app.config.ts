import { ApplicationConfig } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';

import { routes } from './app.routes';
import { ReviewService } from './shared/services/reviews/review.service';
import { ReviewHttpService } from './shared/services/reviews/review-http.service';

export const appConfig: ApplicationConfig = {
  providers: [
    provideRouter(routes),
    provideAnimationsAsync(),
    provideHttpClient(),

    {
      provide: ReviewService,
      useClass: ReviewHttpService,
    },
  ],
};
