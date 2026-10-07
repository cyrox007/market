import BottomBar from './BottomBar';
import { useCounters } from '../../../hooks/useCounters';
import { useAuth } from '../../../hooks/useAuth';

export default function BottomBarConnected() {
  const { cartCount, wishlistCount, compareCount } = useCounters();
  const { isAuthenticated } = useAuth();

  return (
    <BottomBar
      compareCount={compareCount}
      wishlistCount={wishlistCount}
      cartCount={cartCount}
      isAuthenticated={isAuthenticated}
    />
  );
}
