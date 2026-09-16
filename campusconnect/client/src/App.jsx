import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { useAuth } from './lib/store.jsx';
import { Empty, Spinner } from './components/ui.jsx';
import Layout from './components/Layout.jsx';

import Home from './pages/Home.jsx';
import Browse from './pages/Browse.jsx';
import Search from './pages/Search.jsx';
import ListingDetail from './pages/ListingDetail.jsx';
import ListingForm from './pages/ListingForm.jsx';
import Login from './pages/Login.jsx';
import Register from './pages/Register.jsx';
import Profile from './pages/Profile.jsx';
import Settings from './pages/Settings.jsx';

import DashboardLayout from './pages/dashboard/DashboardLayout.jsx';
import Overview from './pages/dashboard/Overview.jsx';
import MyListings from './pages/dashboard/MyListings.jsx';
import Favorites from './pages/dashboard/Favorites.jsx';
import SavedSearches from './pages/dashboard/SavedSearches.jsx';
import Messages from './pages/dashboard/Messages.jsx';
import Applications from './pages/dashboard/Applications.jsx';
import Notifications from './pages/dashboard/Notifications.jsx';
import MyReports from './pages/dashboard/MyReports.jsx';

import AdminLayout from './pages/admin/AdminLayout.jsx';
import AdminOverview from './pages/admin/AdminOverview.jsx';
import AdminVerifications from './pages/admin/AdminVerifications.jsx';
import AdminReports from './pages/admin/AdminReports.jsx';
import AdminUsers from './pages/admin/AdminUsers.jsx';
import AdminListings from './pages/admin/AdminListings.jsx';
import AdminAudit from './pages/admin/AdminAudit.jsx';

function Protected({ children, adminOnly = false }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) return <div className="container page"><Spinner /></div>;
  if (!user) return <Navigate to="/login" state={{ from: location.pathname }} replace />;
  if (adminOnly && user.role !== 'admin') {
    return (
      <div className="container page">
        <Empty icon="🔒" title="Administrators only">
          This area is restricted to designated staff accounts.
        </Empty>
      </div>
    );
  }
  return children;
}

export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<Home />} />
        <Route path="browse/:type" element={<Browse />} />
        <Route path="search" element={<Search />} />
        <Route path="listing/:id" element={<ListingDetail />} />
        <Route path="profile/:id" element={<Profile />} />
        <Route path="login" element={<Login />} />
        <Route path="register" element={<Register />} />

        <Route path="post" element={<Protected><ListingForm /></Protected>} />
        <Route path="settings" element={<Protected><Settings /></Protected>} />

        <Route path="dashboard" element={<Protected><DashboardLayout /></Protected>}>
          <Route index element={<Overview />} />
          <Route path="listings" element={<MyListings />} />
          <Route path="favorites" element={<Favorites />} />
          <Route path="saved-searches" element={<SavedSearches />} />
          <Route path="messages" element={<Messages />} />
          <Route path="messages/:threadKey" element={<Messages />} />
          <Route path="applications" element={<Applications />} />
          <Route path="notifications" element={<Notifications />} />
          <Route path="reports" element={<MyReports />} />
        </Route>

        <Route path="admin" element={<Protected adminOnly><AdminLayout /></Protected>}>
          <Route index element={<AdminOverview />} />
          <Route path="verifications" element={<AdminVerifications />} />
          <Route path="reports" element={<AdminReports />} />
          <Route path="users" element={<AdminUsers />} />
          <Route path="listings" element={<AdminListings />} />
          <Route path="audit" element={<AdminAudit />} />
        </Route>

        <Route
          path="*"
          element={<div className="container page"><Empty icon="🧭" title="Page not found" /></div>}
        />
      </Route>
    </Routes>
  );
}
