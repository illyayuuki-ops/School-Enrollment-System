import { Bell, BookOpenCheck, ChevronDown, ClipboardCheck, FileText, GraduationCap, HelpCircle, LogOut, Menu, Settings2, UsersRound, X } from 'lucide-react';
import { Link, useLocation } from 'wouter';
import { useState } from 'react';
import { useGetSession, useLogout, getGetSessionQueryKey } from '@workspace/api-client-react';
import { useQueryClient } from '@tanstack/react-query';

const navItems = [
  { href: '/dashboard', label: 'Overview', icon: ClipboardCheck },
  { href: '/admission/queue', label: 'Admission queue', icon: ClipboardCheck, roles: ['admin', 'admission'] },
  { href: '/registrar/students', label: 'Student masterlist', icon: UsersRound, roles: ['admin', 'registrar'] },
  { href: '/teacher/classes', label: 'My classes', icon: GraduationCap, roles: ['admin', 'teacher'] },
  { href: '/student/enrollment', label: 'My enrollment', icon: GraduationCap, roles: ['student'] },
  { href: '/student/grades', label: 'Grades', icon: BookOpenCheck, roles: ['student'] },
  { href: '/student/plan-of-study', label: 'Plan of study', icon: ClipboardCheck, roles: ['student'] },
  { href: '/student/academic-policies', label: 'Academic policies', icon: FileText, roles: ['student'] },
  { href: '/student/faq', label: 'FAQ', icon: HelpCircle, roles: ['student'] },
  { href: '/admin/settings', label: 'System controls', icon: Settings2, roles: ['admin'] },
];

export function initials(name?: string) {
  return (name || 'Portal User').split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase();
}

export function PortalShell({ children }: { children: React.ReactNode }) {
  const [location] = useLocation();
  const [mobileOpen, setMobileOpen] = useState(false);
  const queryClient = useQueryClient();
  const session = useGetSession({ query: { retry: false, queryKey: getGetSessionQueryKey() } });
  const logout = useLogout();
  const user = session.data;
  const visibleNav = navItems.filter((item) => !item.roles || (user?.role && item.roles.includes(user.role)));

  const handleLogout = () => {
    logout.mutate(undefined, {
      onSuccess: () => {
        queryClient.removeQueries({ queryKey: getGetSessionQueryKey() });
        window.location.assign('/');
      },
    });
  };

  return (
    <div className="portal-layout">
      <aside className={`portal-sidebar ${mobileOpen ? 'is-open' : ''}`} data-testid="navigation-sidebar">
        <div className="brand-lockup">
          <div className="brand-mark"><GraduationCap size={21} strokeWidth={2.4} /></div>
          <div><div className="brand-name">Northstar</div><div className="brand-caption">Enrollment operations</div></div>
          <button className="icon-button sidebar-close" onClick={() => setMobileOpen(false)} aria-label="Close navigation" data-testid="button-close-navigation"><X size={18} /></button>
        </div>
        <div className="workspace-label">Workspace <span>AY {new Date().getFullYear()}–{String(new Date().getFullYear() + 1).slice(-2)}</span></div>
        <nav className="side-nav" aria-label="Primary navigation">
          {visibleNav.map((item) => {
            const Icon = item.icon;
            const active = location === item.href;
            return <Link href={item.href} key={item.href} className={`side-nav-link ${active ? 'active' : ''}`} onClick={() => setMobileOpen(false)} data-testid={`link-nav-${item.label.toLowerCase().replaceAll(' ', '-')}`}>
              <Icon size={17} /><span>{item.label}</span>{item.href === '/admission/queue' && <span className="nav-count">4</span>}
            </Link>;
          })}
        </nav>
        <div className="sidebar-footnote">
          <div className="signal"><span className="signal-dot" /> Systems operational</div>
          <span className="mono">v2.4.1</span>
        </div>
      </aside>
      {mobileOpen && <button className="mobile-scrim" onClick={() => setMobileOpen(false)} aria-label="Close menu" data-testid="button-close-menu-scrim" />}
      <div className="portal-main">
        <header className="topbar">
          <button className="icon-button menu-trigger" onClick={() => setMobileOpen(true)} aria-label="Open navigation" data-testid="button-open-navigation"><Menu size={20} /></button>
          <div className="breadcrumb"><span>Northstar</span><span className="crumb-divider">/</span><strong>{location === '/dashboard' ? 'Overview' : visibleNav.find((item) => item.href === location)?.label || 'Workspace'}</strong></div>
          <div className="topbar-actions">
            <button className="notification-button" aria-label="Notifications" data-testid="button-notifications"><Bell size={18} /><span className="notification-dot" /></button>
            <div className="user-menu">
              <div className="avatar avatar-small">{initials(user?.fullName)}</div>
              <div className="user-summary"><strong data-testid="text-user-name">{user?.fullName || 'Loading account'}</strong><span>{user?.role || 'workspace'} account</span></div>
              <ChevronDown size={15} className="muted-icon" />
              <button className="logout-button" onClick={handleLogout} disabled={logout.isPending} data-testid="button-logout"><LogOut size={15} /> <span>Sign out</span></button>
            </div>
          </div>
        </header>
        <main className="page-content">{children}</main>
      </div>
    </div>
  );
}