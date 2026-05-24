import type { SVGProps } from 'react';

const stroke = {
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.8,
  strokeLinecap: 'round' as const,
  strokeLinejoin: 'round' as const,
};

export function IconSearch(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={20} height={20} {...stroke} {...props}>
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
  );
}

export function IconStar(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={16} height={16} fill="currentColor" {...props}>
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21l1.18-6.88-5-4.87 6.91-1.01L12 2z" />
    </svg>
  );
}

export function IconCheck(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={14} height={14} {...stroke} {...props}>
      <path d="M5 12l4 4L19 7" />
    </svg>
  );
}

export function IconVerified(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={16} height={16} fill="currentColor" {...props}>
      <path d="M12 2l2.39 2.05 3.13-.45.65 3.1 2.83 1.43-1.43 2.83.45 3.13L17.97 15 17.5 18.13l-3.13.45L12 22l-2.39-2.42-3.13-.45L6 18.13 3.43 16.7l1.43-2.83-.45-3.13L7.34 8.84l1.43-2.83L11.6 4.6 12 2z" />
      <path d="M9 12l2 2 4-4" stroke="white" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function IconLocation(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={14} height={14} {...stroke} {...props}>
      <path d="M12 22s7-7.58 7-13a7 7 0 1 0-14 0c0 5.42 7 13 7 13z" />
      <circle cx="12" cy="9" r="2.5" />
    </svg>
  );
}

export function IconClock(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={14} height={14} {...stroke} {...props}>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 7v5l3 2" />
    </svg>
  );
}

export function IconChat(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={18} height={18} {...stroke} {...props}>
      <path d="M21 12a8.5 8.5 0 0 1-13.04 7.18L3 21l1.83-4.96A8.5 8.5 0 1 1 21 12z" />
    </svg>
  );
}

export function IconUsers(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={18} height={18} {...stroke} {...props}>
      <circle cx="9" cy="8" r="3.5" />
      <path d="M2.5 20a6.5 6.5 0 0 1 13 0" />
      <circle cx="17" cy="9" r="2.5" />
      <path d="M21.5 18a5 5 0 0 0-5-5" />
    </svg>
  );
}

export function IconBolt(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={18} height={18} {...stroke} {...props}>
      <path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z" />
    </svg>
  );
}

export function IconShield(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={18} height={18} {...stroke} {...props}>
      <path d="M12 2 4 5v7c0 5 3.4 9.3 8 10 4.6-.7 8-5 8-10V5l-8-3z" />
    </svg>
  );
}

export function IconMenu(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={22} height={22} {...stroke} {...props}>
      <path d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  );
}

export function IconFilter(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={16} height={16} {...stroke} {...props}>
      <path d="M3 5h18M6 12h12M10 19h4" />
    </svg>
  );
}

export function IconTiktok(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={16} height={16} fill="currentColor" {...props}>
      <path d="M19.6 6.7a5 5 0 0 1-3-1.7V15a5.3 5.3 0 1 1-4.6-5.2v2.6a2.7 2.7 0 1 0 1.9 2.6V2h2.7a5 5 0 0 0 3 4.4v.3z" />
    </svg>
  );
}
export function IconInstagram(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={16} height={16} {...stroke} {...props}>
      <rect x="3" y="3" width="18" height="18" rx="5" />
      <circle cx="12" cy="12" r="4" />
      <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
    </svg>
  );
}
export function IconYoutube(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={18} height={18} fill="currentColor" {...props}>
      <path d="M23 7.5a3 3 0 0 0-2.1-2.1C19 5 12 5 12 5s-7 0-8.9.4A3 3 0 0 0 1 7.5C.6 9.4.6 12 .6 12s0 2.6.4 4.5a3 3 0 0 0 2.1 2.1C5 19 12 19 12 19s7 0 8.9-.4a3 3 0 0 0 2.1-2.1C23.4 14.6 23.4 12 23.4 12s0-2.6-.4-4.5zM10 15.5v-7l6 3.5-6 3.5z" />
    </svg>
  );
}
export function IconFacebook(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={16} height={16} fill="currentColor" {...props}>
      <path d="M13 22v-8h3l.5-4H13V7.5c0-1.2.3-2 2-2h2V2.1C16.6 2 15.5 2 14.2 2 11.4 2 9.5 3.7 9.5 6.9V10H6v4h3.5v8H13z" />
    </svg>
  );
}
export function IconLinkedin(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width={16} height={16} fill="currentColor" {...props}>
      <path d="M4 4a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-2 6h4v12H2V10zm6 0h4v2c.7-1.2 2-2 4-2 3 0 5 2 5 5v7h-4v-6c0-1.7-.6-3-2.5-3-1.6 0-2.5 1.1-2.5 3v6H8V10z" />
    </svg>
  );
}

export function PlatformIcon({ platform, ...props }: { platform: string } & SVGProps<SVGSVGElement>) {
  switch (platform) {
    case 'tiktok':
      return <IconTiktok {...props} />;
    case 'instagram':
      return <IconInstagram {...props} />;
    case 'youtube':
      return <IconYoutube {...props} />;
    case 'facebook':
      return <IconFacebook {...props} />;
    case 'linkedin':
      return <IconLinkedin {...props} />;
    default:
      return null;
  }
}
