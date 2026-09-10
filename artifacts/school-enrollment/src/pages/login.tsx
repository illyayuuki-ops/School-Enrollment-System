import { FormEvent, useState } from 'react';
import { ArrowRight, Check, Eye, EyeOff, GraduationCap, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { useLocation } from 'wouter';
import { getHealthCheckQueryKey, useHealthCheck, useLogin } from '@workspace/api-client-react';

const roles = [
  { id: 'admission', label: 'Admissions', detail: 'Review new applications' },
  { id: 'registrar', label: 'Registrar', detail: 'Maintain student records' },
  { id: 'teacher', label: 'Faculty', detail: 'Manage classes and rosters' },
  { id: 'student', label: 'Student', detail: 'View your enrollment' },
  { id: 'admin', label: 'Administrator', detail: 'Configure the workspace' },
] as const;

export default function Login() {
  const [, setLocation] = useLocation();
  const login = useLogin();
  const health = useHealthCheck({ query: { retry: 1, queryKey: getHealthCheckQueryKey() } });
  const [role, setRole] = useState<typeof roles[number]['id']>('admission');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [forgotOpen, setForgotOpen] = useState(false);

  const submit = (event: FormEvent) => {
    event.preventDefault();
    login.mutate({ data: { email, password, role } }, { onSuccess: () => setLocation(role === 'student' ? '/student/enrollment' : '/dashboard') });
  };

  return (
    <div className="login-page">
      <section className="login-story">
        <div className="login-story-inner">
          <div className="brand-lockup login-brand"><div className="brand-mark"><GraduationCap size={21} /></div><div><div className="brand-name">Northstar</div><div className="brand-caption">Enrollment operations</div></div></div>
          <div className="story-copy">
            <div className="eyebrow eyebrow-light">The clear path forward</div>
            <h1>From first inquiry<br />to <em>fully enrolled.</em></h1>
            <p>A focused operations workspace for the people who keep a school moving.</p>
          </div>
          <div className="story-proof">
            <div className="proof-item"><span className="proof-number">01</span><span>One source of truth for every applicant.</span></div>
            <div className="proof-item"><span className="proof-number">02</span><span>Context that follows the student forward.</span></div>
          </div>
          <div className="login-geometry" aria-hidden="true"><span /><span /><span /></div>
           <div className="story-footer"><span>Academic year 2026–2027</span><span>Secure workspace</span></div>
        </div>
      </section>
      <section className="login-panel">
        <div className="login-form-wrap">
          <div className="mobile-login-mark"><div className="brand-mark"><GraduationCap size={21} /></div><span>Northstar</span></div>
          <div className="form-heading"><div className="eyebrow">Welcome back</div><h2>Sign in to your workspace</h2><p>Select your team context to continue.</p></div>
          <div className="role-grid" role="radiogroup" aria-label="Choose account role">
            {roles.map((item) => <button type="button" key={item.id} className={`role-card ${role === item.id ? 'selected' : ''}`} onClick={() => setRole(item.id)} data-testid={`button-role-${item.id}`}>
              <span className="role-radio">{role === item.id && <Check size={12} strokeWidth={3} />}</span><span><strong>{item.label}</strong><small>{item.detail}</small></span>
            </button>)}
          </div>
          <form onSubmit={submit} className="login-form">
            <input type="hidden" name="user_role" value={role} />
            <label className="field-label" htmlFor="email">Work email</label>
            <div className="field-with-icon"><Mail size={16} /><input id="email" type="email" required value={email} onChange={(event) => setEmail(event.target.value)} placeholder="you@school.edu" data-testid="input-email" /></div>
            <div className="field-row"><label className="field-label" htmlFor="password">Password</label><button type="button" className="text-button" onClick={() => setForgotOpen(true)} data-testid="button-forgot-password">Forgot password?</button></div>
            <div className="field-with-icon"><LockKeyhole size={16} /><input id="password" type={showPassword ? 'text' : 'password'} minLength={8} required value={password} onChange={(event) => setPassword(event.target.value)} placeholder="Enter your password" data-testid="input-password" /><button type="button" className="password-toggle" onClick={() => setShowPassword((value) => !value)} aria-label={showPassword ? 'Hide password' : 'Show password'} data-testid="button-toggle-password">{showPassword ? <EyeOff size={16} /> : <Eye size={16} />}</button></div>
            {login.isError && <div className="form-error" role="alert" data-testid="status-login-error">We couldn't sign you in. Check your details and try again.</div>}
            <button className="primary-button login-submit" type="submit" disabled={login.isPending} data-testid="button-submit-login"><span>{login.isPending ? 'Signing in…' : 'Continue to Northstar'}</span><ArrowRight size={17} /></button>
          </form>
          <div className="secure-note"><ShieldCheck size={16} /><span>Your session is protected with school-grade access controls.</span><span className={`health-pill ${health.isError ? 'offline' : ''}`}><i /> {health.isLoading ? 'Checking systems' : health.isError ? 'Check systems' : 'Systems ready'}</span></div>
          <p className="login-legal">By continuing, you agree to your institution’s acceptable use policy.</p>
        </div>
      </section>
      {forgotOpen && <div className="modal-backdrop" role="presentation" onClick={(event) => { if (event.target === event.currentTarget) setForgotOpen(false); }}>
        <section className="recovery-modal" role="dialog" aria-modal="true" aria-labelledby="recovery-title">
          <button type="button" className="modal-close" onClick={() => setForgotOpen(false)} aria-label="Close recovery dialog">×</button>
          <div className="eyebrow">Credential recovery</div>
          <h2 id="recovery-title">Need help signing in?</h2>
          <p>Contact the registrar&apos;s office with your school email and selected workspace role. They can verify your identity and reset your access.</p>
          <a className="primary-button recovery-link" href="mailto:registrar@northstar.edu">Email registrar&apos;s office</a>
        </section>
      </div>}
    </div>
  );
}