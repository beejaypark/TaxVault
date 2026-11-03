import { Injectable, inject } from '@angular/core';
import { NavigationEnd, Router } from '@angular/router';
import { filter } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class NavigationHistoryService {
  private readonly router = inject(Router);
  private readonly history: string[] = [];
  private currentUrl = this.router.url;
  private isNavigatingBack = false;
  private primaryNavigationTarget: string | null = null;

  constructor() {
    this.router.events
      .pipe(filter((event): event is NavigationEnd => event instanceof NavigationEnd))
      .subscribe((event) => {
        const nextUrl = event.urlAfterRedirects;

        if (this.isNavigatingBack) {
          this.isNavigatingBack = false;
        } else if (
          this.currentUrl
          && this.currentUrl !== nextUrl
          && this.pathOf(nextUrl) !== this.primaryNavigationTarget
        ) {
          this.history.push(this.currentUrl);
          if (this.history.length > 30) {
            this.history.shift();
          }
        }

        this.primaryNavigationTarget = null;
        this.currentUrl = nextUrl;
      });
  }

  get isVisible(): boolean {
    return this.history.length > 0;
  }

  get backLabel(): string {
    const targetUrl = this.targetUrl;
    const label = this.pageLabel(targetUrl);

    return label ? `Back to ${label}` : 'Back';
  }

  goBack(): void {
    const targetUrl = this.targetUrl;

    if (!targetUrl || targetUrl === this.currentUrl) {
      return;
    }

    if (this.history.length > 0) {
      this.history.pop();
    }

    this.isNavigatingBack = true;
    void this.router.navigateByUrl(targetUrl);
  }

  clearForPrimaryNavigation(targetPath: string): void {
    this.history.length = 0;
    this.primaryNavigationTarget = this.currentPath === targetPath
      ? null
      : targetPath;
  }

  private get targetUrl(): string | null {
    return this.history[this.history.length - 1] ?? null;
  }

  private get currentPath(): string {
    return this.pathOf(this.currentUrl);
  }

  private pathOf(url: string): string {
    return url.split(/[?#]/, 1)[0] || '/';
  }

  private pageLabel(url: string | null): string {
    if (!url) {
      return '';
    }

    const path = this.pathOf(url);

    if (/^\/reviews\/[^/]+$/.test(path)) {
      return 'Review details';
    }

    const labels: Record<string, string> = {
      '/dashboard': 'Dashboard',
      '/documents': 'Documents',
      '/documents/upload': 'Upload documents',
      '/financial-years': 'Financial years',
      '/properties': 'Properties',
      '/investments': 'Investments',
      '/transactions': 'Income & expenses',
      '/reviews': 'Reviews',
      '/tax-return': 'Tax return pack',
    };

    return labels[path] ?? 'previous page';
  }
}
