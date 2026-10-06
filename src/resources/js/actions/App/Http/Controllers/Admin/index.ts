import DashboardController from './DashboardController'
import AuditLogController from './AuditLogController'
import UserController from './UserController'
import SystemHealthController from './SystemHealthController'
import DisputeController from './DisputeController'
import SanctionController from './SanctionController'
import FailedJobController from './FailedJobController'

const Admin = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    AuditLogController: Object.assign(AuditLogController, AuditLogController),
    UserController: Object.assign(UserController, UserController),
    SystemHealthController: Object.assign(SystemHealthController, SystemHealthController),
    DisputeController: Object.assign(DisputeController, DisputeController),
    SanctionController: Object.assign(SanctionController, SanctionController),
    FailedJobController: Object.assign(FailedJobController, FailedJobController),
}

export default Admin