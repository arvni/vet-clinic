import type { ReactNode, SVGProps } from 'react'

function Icon({ children, ...props }: SVGProps<SVGSVGElement> & { children: ReactNode }) {
  return (
    <svg
      viewBox="0 0 24 24"
      width="24"
      height="24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      {...props}
    >
      {children}
    </svg>
  )
}

type P = SVGProps<SVGSVGElement>

export const HomeIcon = (p: P) => (
  <Icon {...p}>
    <path d="M3 11l9-8 9 8" />
    <path d="M5 10v10h14V10" />
    <path d="M10 20v-6h4v6" />
  </Icon>
)

export const CalendarIcon = (p: P) => (
  <Icon {...p}>
    <rect x="3" y="5" width="18" height="16" rx="3" />
    <path d="M3 10h18M8 3v4M16 3v4" />
  </Icon>
)

export const PawIcon = (p: P) => (
  <Icon {...p}>
    <circle cx="6" cy="11" r="1.8" />
    <circle cx="10" cy="6.5" r="1.8" />
    <circle cx="14" cy="6.5" r="1.8" />
    <circle cx="18" cy="11" r="1.8" />
    <path d="M12 11c-3 0-5.5 3.2-5.5 6 0 2 1.6 3 3.2 2.6.8-.2 1.5-.5 2.3-.5s1.5.3 2.3.5c1.6.4 3.2-.6 3.2-2.6 0-2.8-2.5-6-5.5-6z" />
  </Icon>
)

export const PlusIcon = (p: P) => (
  <Icon {...p}>
    <path d="M12 5v14M5 12h14" />
  </Icon>
)

export const ClinicIcon = (p: P) => (
  <Icon {...p}>
    <path d="M4 21V8l8-5 8 5v13z" />
    <path d="M12 9v6M9 12h6" />
  </Icon>
)

export const VideoIcon = (p: P) => (
  <Icon {...p}>
    <rect x="3" y="6" width="13" height="12" rx="3" />
    <path d="M16 11l5-3v8l-5-3" />
  </Icon>
)

export const PhoneIcon = (p: P) => (
  <Icon {...p}>
    <path d="M5 3h4l2 5-3 2a12 12 0 006 6l2-3 5 2v4a2 2 0 01-2 2A17 17 0 013 5a2 2 0 012-2z" />
  </Icon>
)

export const AlertIcon = (p: P) => (
  <Icon {...p}>
    <path d="M12 3l10 18H2z" />
    <path d="M12 10v5M12 18h.01" />
  </Icon>
)

export const CheckIcon = (p: P) => (
  <Icon {...p}>
    <path d="M5 12l5 5 9-10" />
  </Icon>
)

/** Points "forward" in the reading direction, so it flips in RTL. */
export const ChevronIcon = (p: P) => (
  <Icon {...p} className={`rtl:-scale-x-100 ${p.className ?? ''}`}>
    <path d="M9 5l7 7-7 7" />
  </Icon>
)

export const LogoutIcon = (p: P) => (
  <Icon {...p} className={`rtl:-scale-x-100 ${p.className ?? ''}`}>
    <path d="M10 4H5v16h5M15 8l4 4-4 4M19 12H9" />
  </Icon>
)
