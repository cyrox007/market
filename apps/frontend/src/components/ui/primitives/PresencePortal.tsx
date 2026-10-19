import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { usePresence, type PresenceMotion } from './usePresence';

interface PresencePortalProps {
  open: boolean;
  motion: PresenceMotion;
  children: (state: { shown: boolean; leaving: boolean }) => ReactNode;
}

/** Окно в body, пока не уедет: общее у Sheet и Popover (market-docs/48) */
export default function PresencePortal({ open, motion, children }: PresencePortalProps) {
  const { rendered, shown, leaving } = usePresence(open, motion.leaveMs);
  if (!rendered) return null;
  return createPortal(children({ shown, leaving }), document.body);
}
