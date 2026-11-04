import { inject } from '@angular/core';
import { CanActivateChildFn, CanActivateFn, Router } from '@angular/router';
import { map } from 'rxjs';

import { AuthService } from '../services/auth/auth.service';

export const authGuard: CanActivateChildFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.initialize().pipe(
    map((user) =>
      user
        ? true
        : router.createUrlTree(['/login'], {
            queryParams: { returnUrl: state.url },
          }),
    ),
  );
};

export const ownerGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.canEdit ? true : router.createUrlTree(['/dashboard']);
};
