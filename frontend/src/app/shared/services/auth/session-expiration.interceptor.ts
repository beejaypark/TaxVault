import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';

export const sessionExpirationInterceptor: HttpInterceptorFn = (request, next) => {
  if (!request.url.startsWith('/api/')) return next(request);

  const auth = inject(AuthService);
  const router = inject(Router);

  return next(request).pipe(catchError((error: HttpErrorResponse) => {
    const expired = error.status === 401
      || (error.status === 419 && request.url === '/api/auth/activity');
    if (expired && auth.currentUser) {
      const returnUrl = router.url;
      auth.clearSession();
      void router.navigate(['/login'], {
        queryParams: { returnUrl, sessionExpired: '1' },
        replaceUrl: true,
      });
    }

    return throwError(() => error);
  }));
};
