import ProfileController from './ProfileController'
import PrivacyController from './PrivacyController'
import SecurityController from './SecurityController'

const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
    PrivacyController: Object.assign(PrivacyController, PrivacyController),
    SecurityController: Object.assign(SecurityController, SecurityController),
}

export default Settings