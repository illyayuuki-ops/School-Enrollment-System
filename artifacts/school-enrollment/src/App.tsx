import { type ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ErrorBoundary } from '@/components/error-boundary';
import { Toaster } from '@/components/ui/toaster';
import { TooltipProvider } from '@/components/ui/tooltip';
import NotFound from '@/pages/not-found';
import Login from '@/pages/login';
import Dashboard from '@/pages/dashboard';
import AdmissionQueue from '@/pages/admission-queue';
import RegistrarStudents from '@/pages/registrar-students';
import { AdminSettings, StudentEnrollment, TeacherClasses } from '@/pages/role-pages';
import { PortalShell } from '@/components/portal-shell';
import {
  Route,
  Switch,
  useLocation,
  Router as WouterRouter,
} from 'wouter';

const queryClient = new QueryClient();

function Router() {
  return (
    // Keep a shared shell (sidebar, navbar) outside the boundary so it
    // survives a page crash.
    <RoutedErrorBoundary>
      <Switch>
        <Route path="/" component={Login} />
        <Route path="/dashboard"><PortalShell><Dashboard /></PortalShell></Route>
        <Route path="/admission/queue"><PortalShell><AdmissionQueue /></PortalShell></Route>
        <Route path="/registrar/students"><PortalShell><RegistrarStudents /></PortalShell></Route>
        <Route path="/teacher/classes"><PortalShell><TeacherClasses /></PortalShell></Route>
        <Route path="/student/enrollment"><PortalShell><StudentEnrollment /></PortalShell></Route>
        <Route path="/admin/settings"><PortalShell><AdminSettings /></PortalShell></Route>
        <Route component={NotFound} />
      </Switch>
    </RoutedErrorBoundary>
  );
}

function RoutedErrorBoundary({ children }: { children: ReactNode }) {
  const [location] = useLocation();
  return <ErrorBoundary resetKey={location}>{children}</ErrorBoundary>;
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <WouterRouter base={import.meta.env.BASE_URL.replace(/\/$/, '')}>
          <Router />
        </WouterRouter>
        <Toaster />
      </TooltipProvider>
    </QueryClientProvider>
  );
}

export default App;
