import type { NotificationSettings, User, ProfileUpdateData } from '../../lib/api';
import AccountProfileForm from './AccountProfileForm';
import AccountPasswordForm from './AccountPasswordForm';
import AccountNotifications from './AccountNotifications';
import AccountEmailSettings from './AccountEmailSettings';
interface Props {
  user: Pick<User, 'id' | 'name' | 'email' | 'phone' | 'first_name' | 'last_name' | 'patronymic'>;
  onSaveProfile: (data: ProfileUpdateData) => Promise<unknown>;
  notifications?: NotificationSettings;
  isLoadingNotifications: boolean;
  notificationsError?: unknown;
  onRetryNotifications: () => unknown;
  onSaveNotifications: (patch: Partial<NotificationSettings>) => Promise<unknown>;
}
export default function AccountSettings(props: Props) {
  return (
    <div className="grid min-w-0 grid-cols-2 gap-6 max-md:grid-cols-1">
      <AccountProfileForm user={props.user} onSave={props.onSaveProfile} />
      <AccountPasswordForm />
      <div className="col-span-full min-w-0">
        <AccountEmailSettings userId={props.user.id} email={props.user.email} />
      </div>
      <div className="col-span-full min-w-0">
        <AccountNotifications
          settings={props.notifications}
          isLoading={props.isLoadingNotifications}
          error={props.notificationsError}
          onRetry={props.onRetryNotifications}
          onSave={props.onSaveNotifications}
        />
      </div>
    </div>
  );
}
