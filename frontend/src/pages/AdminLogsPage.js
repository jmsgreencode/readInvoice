export default function AdminLogsPage() {
  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Admin Logs</h1>
        <p className="page-header__subtitle">System activity and event logs</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">Log Viewer</span>
        </div>
        <div className="admin-logs__body">
          <div className="admin-logs__entry admin-logs__entry--info">
            [INFO] Log viewer placeholder - connect to backend log endpoint to display real entries.
          </div>
        </div>
      </div>
    </>
  );
}
