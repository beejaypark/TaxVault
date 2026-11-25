import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

export type AnnouncementTheme = 'blue' | 'teal' | 'amber' | 'red' | 'violet';
export type AnnouncementIcon = 'megaphone' | 'info' | 'warning' | 'celebration' | 'none';
export type AnnouncementFont = 'system' | 'serif' | 'mono';

export interface Announcement {
  id: string;
  title: string;
  message: string;
  theme: AnnouncementTheme;
  icon: AnnouncementIcon;
  font_family: AnnouncementFont;
  is_bold: boolean;
  is_dismissible: boolean;
  action_label: string | null;
  action_url: string | null;
  is_published: boolean;
  starts_at: string | null;
  ends_at: string | null;
  updated_at: string;
}

export type AnnouncementInput = Omit<Announcement, 'id' | 'updated_at'>;

@Injectable({ providedIn: 'root' })
export class AnnouncementService {
  private readonly http = inject(HttpClient);

  active(): Observable<{ data: Announcement[] }> {
    return this.http.get<{ data: Announcement[] }>('/api/announcements/active');
  }

  list(): Observable<{ data: Announcement[] }> {
    return this.http.get<{ data: Announcement[] }>('/api/admin/announcements');
  }

  create(input: AnnouncementInput): Observable<{ data: Announcement }> {
    return this.http.post<{ data: Announcement }>('/api/admin/announcements', input);
  }

  update(id: string, input: AnnouncementInput): Observable<{ data: Announcement }> {
    return this.http.put<{ data: Announcement }>(`/api/admin/announcements/${id}`, input);
  }

  delete(id: string): Observable<{ data: { id: string } }> {
    return this.http.delete<{ data: { id: string } }>(`/api/admin/announcements/${id}`);
  }
}
