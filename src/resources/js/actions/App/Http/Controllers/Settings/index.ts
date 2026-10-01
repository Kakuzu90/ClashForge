import EmailPreferenceController from './EmailPreferenceController'
import ProfileController from './ProfileController'
import PrivacyController from './PrivacyController'
import SecurityController from './SecurityController'
import AccountDeletionController from './AccountDeletionController'
import EmailChangeController from './EmailChangeController'

const Settings = {
    EmailPreferenceController: Object.assign(EmailPreferenceController, EmailPreferenceController),
    ProfileController: Object.assign(ProfileController, ProfileController),
    PrivacyController: Object.assign(PrivacyController, PrivacyController),
    SecurityController: Object.assign(SecurityController, SecurityController),
    AccountDeletionController: Object.assign(AccountDeletionController, AccountDeletionController),
    EmailChangeController: Object.assign(EmailChangeController, EmailChangeController),
}

export default Settings