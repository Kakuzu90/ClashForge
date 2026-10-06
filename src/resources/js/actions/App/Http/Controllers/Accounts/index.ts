import AttachController from './AttachController'
import VerificationController from './VerificationController'
import AccountOwnershipController from './AccountOwnershipController'
import AccountImageController from './AccountImageController'
import AccountController from './AccountController'

const Accounts = {
    AttachController: Object.assign(AttachController, AttachController),
    VerificationController: Object.assign(VerificationController, VerificationController),
    AccountOwnershipController: Object.assign(AccountOwnershipController, AccountOwnershipController),
    AccountImageController: Object.assign(AccountImageController, AccountImageController),
    AccountController: Object.assign(AccountController, AccountController),
}

export default Accounts