import { AfterViewInit, Directive, ElementRef, Renderer2 } from '@angular/core';

@Directive({
  selector: '[tvCollapsibleCard]',
  standalone: true,
})
export class CollapsibleCardDirective implements AfterViewInit {
  private expanded = true;

  constructor(
    private readonly element: ElementRef<HTMLElement>,
    private readonly renderer: Renderer2,
  ) {}

  ngAfterViewInit(): void {
    const host = this.element.nativeElement;
    const content = this.renderer.createElement('div') as HTMLDivElement;
    this.renderer.addClass(content, 'tv-collapsible-content');
    let retainedElement = false;
    for (const child of Array.from(host.childNodes)) {
      const isPersistentHeader = child instanceof HTMLElement && child.hasAttribute('data-tv-collapse-header');
      const isFirstElement = child instanceof HTMLElement && !retainedElement;
      if (isPersistentHeader || isFirstElement) {
        retainedElement = true;
      } else {
        this.renderer.appendChild(content, child);
      }
    }

    const toggle = this.renderer.createElement('button') as HTMLButtonElement;
    this.renderer.setAttribute(toggle, 'type', 'button');
    this.renderer.addClass(toggle, 'tv-collapse-toggle');
    this.renderer.setAttribute(toggle, 'aria-expanded', 'true');
    this.renderer.setAttribute(toggle, 'aria-label', 'Collapse card');
    this.renderer.setAttribute(toggle, 'title', 'Collapse card');
    const chevron = this.renderer.createElement('span') as HTMLSpanElement;
    this.renderer.addClass(chevron, 'tv-chevron');
    this.renderer.appendChild(toggle, chevron);
    this.renderer.listen(toggle, 'click', () => this.toggle(toggle, content));

    this.renderer.addClass(host, 'tv-collapsible-card');
    this.renderer.appendChild(host, toggle);
    this.renderer.appendChild(host, content);
  }

  private toggle(toggle: HTMLButtonElement, content: HTMLDivElement): void {
    this.expanded = !this.expanded;
    this.renderer.setAttribute(toggle, 'aria-expanded', String(this.expanded));
    const label = this.expanded ? 'Collapse card' : 'Expand card';
    this.renderer.setAttribute(toggle, 'aria-label', label);
    this.renderer.setAttribute(toggle, 'title', label);
    this.renderer.removeClass(toggle, 'is-collapsed');
    if (!this.expanded) {
      this.renderer.addClass(toggle, 'is-collapsed');
    }
    this.renderer.setProperty(content, 'hidden', !this.expanded);
  }
}
