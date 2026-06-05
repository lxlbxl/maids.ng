import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { toast } from 'sonner';
import {
    Users,
    UserCheck,
    Clock,
    XCircle,
    CheckCircle,
    Search,
    Filter,
    Eye,
    RefreshCw,
    AlertCircle,
} from 'lucide-react';

export default function Assignments({ auth, assignments: initialAssignments, stats }) {
    const [assignments, setAssignments] = useState(initialAssignments?.data || []);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [typeFilter, setTypeFilter] = useState('all');
    const [selectedAssignment, setSelectedAssignment] = useState(null);

    const statusColors = {
        pending_acceptance: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        accepted: 'bg-blue-100 text-blue-800 border-blue-200',
        active: 'bg-green-100 text-green-800 border-green-200',
        completed: 'bg-gray-100 text-gray-800 border-gray-200',
        rejected: 'bg-red-100 text-red-800 border-red-200',
        cancelled: 'bg-gray-100 text-gray-600 border-gray-200',
        timeout: 'bg-orange-100 text-orange-800 border-orange-200',
    };

    const statusLabels = {
        pending_acceptance: 'Pending Acceptance',
        accepted: 'Accepted',
        active: 'Active',
        completed: 'Completed',
        rejected: 'Rejected',
        cancelled: 'Cancelled',
        timeout: 'Timeout',
    };

    const typeLabels = {
        direct_selection: 'Direct Selection',
        guarantee_match: 'Guarantee Match',
        replacement: 'Replacement',
    };

    const filteredAssignments = assignments.filter((assignment) => {
        const matchesSearch =
            searchTerm === '' ||
            assignment.employer?.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            assignment.maid?.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            assignment.id.toString().includes(searchTerm);

        const matchesStatus = statusFilter === 'all' || assignment.status === statusFilter;
        const matchesType = typeFilter === 'all' || assignment.type === typeFilter;

        return matchesSearch && matchesStatus && matchesType;
    });

    const handleRefresh = async () => {
        setLoading(true);
        try {
            const response = await fetch('/admin/assignments?format=json');
            const data = await response.json();
            setAssignments(data.assignments.data);
            toast.success('Assignments refreshed');
        } catch (error) {
            toast.error('Failed to refresh assignments');
        } finally {
            setLoading(false);
        }
    };

    const handleCancelAssignment = async (assignmentId) => {
        if (!confirm('Are you sure you want to cancel this assignment?')) return;

        try {
            const response = await fetch(`/admin/assignments/${assignmentId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            });

            if (response.ok) {
                toast.success('Assignment cancelled successfully');
                handleRefresh();
            } else {
                toast.error('Failed to cancel assignment');
            }
        } catch (error) {
            toast.error('An error occurred');
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const formatDateTime = (dateString) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AdminLayout auth={auth}>
            <Head title="Assignments Management" />

            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Assignments Management</h1>
                        <p className="text-gray-500 mt-1">Manage maid assignments and track their status</p>
                    </div>
                    <Button onClick={handleRefresh} disabled={loading} variant="outline">
                        <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">Total Assignments</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats?.total || 0}</p>
                                </div>
                                <Users className="w-8 h-8 text-blue-500" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">Active</p>
                                    <p className="text-2xl font-bold text-green-600">{stats?.active || 0}</p>
                                </div>
                                <UserCheck className="w-8 h-8 text-green-500" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">Pending</p>
                                    <p className="text-2xl font-bold text-yellow-600">{stats?.pending || 0}</p>
                                </div>
                                <Clock className="w-8 h-8 text-yellow-500" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">Rejected/Timeout</p>
                                    <p className="text-2xl font-bold text-red-600">{stats?.rejected || 0}</p>
                                </div>
                                <XCircle className="w-8 h-8 text-red-500" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-col md:flex-row gap-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                                <Input
                                    placeholder="Search by employer, maid, or ID..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger className="w-[180px]">
                                    <Filter className="w-4 h-4 mr-2" />
                                    <SelectValue placeholder="Filter by status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                    <SelectItem value="pending_acceptance">Pending Acceptance</SelectItem>
                                    <SelectItem value="accepted">Accepted</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                    <SelectItem value="rejected">Rejected</SelectItem>
                                    <SelectItem value="cancelled">Cancelled</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={typeFilter} onValueChange={setTypeFilter}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Filter by type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    <SelectItem value="direct_selection">Direct Selection</SelectItem>
                                    <SelectItem value="guarantee_match">Guarantee Match</SelectItem>
                                    <SelectItem value="replacement">Replacement</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Assignments Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Assignments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>ID</TableHead>
                                    <TableHead>Employer</TableHead>
                                    <TableHead>Maid</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Start Date</TableHead>
                                    <TableHead>Deadline</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredAssignments.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-center py-8 text-gray-500">
                                            No assignments found
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    filteredAssignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell className="font-medium">#{assignment.id}</TableCell>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium">{assignment.employer?.name}</p>
                                                    <p className="text-sm text-gray-500">{assignment.employer?.phone}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium">{assignment.maid?.name}</p>
                                                    <p className="text-sm text-gray-500">{assignment.maid?.phone}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell>{typeLabels[assignment.type] || assignment.type}</TableCell>
                                            <TableCell>
                                                <Badge className={statusColors[assignment.status]}>
                                                    {statusLabels[assignment.status] || assignment.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{formatDate(assignment.start_date)}</TableCell>
                                            <TableCell>
                                                {assignment.employer_response_deadline ? (
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="w-3 h-3 text-gray-400" />
                                                        <span
                                                            className={
                                                                assignment.is_overdue ? 'text-red-600 font-medium' : ''
                                                            }
                                                        >
                                                            {formatDateTime(assignment.employer_response_deadline)}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    'N/A'
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Dialog>
                                                        <DialogTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => setSelectedAssignment(assignment)}
                                                            >
                                                                <Eye className="w-4 h-4" />
                                                            </Button>
                                                        </DialogTrigger>
                                                        <DialogContent className="max-w-2xl">
                                                            <DialogHeader>
                                                                <DialogTitle>Assignment Details #{assignment.id}</DialogTitle>
                                                                <DialogDescription>
                                                                    Full details of this assignment
                                                                </DialogDescription>
                                                            </DialogHeader>
                                                            <div className="space-y-4 mt-4">
                                                                <div className="grid grid-cols-2 gap-4">
                                                                    <div>
                                                                        <p className="text-sm text-gray-500">Employer</p>
                                                                        <p className="font-medium">{assignment.employer?.name}</p>
                                                                        <p className="text-sm">{assignment.employer?.email}</p>
                                                                        <p className="text-sm">{assignment.employer?.phone}</p>
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-sm text-gray-500">Maid</p>
                                                                        <p className="font-medium">{assignment.maid?.name}</p>
                                                                        <p className="text-sm">{assignment.maid?.email}</p>
                                                                        <p className="text-sm">{assignment.maid?.phone}</p>
                                                                    </div>
                                                                </div>
                                                                <div className="grid grid-cols-2 gap-4">
                                                                    <div>
                                                                        <p className="text-sm text-gray-500">Status</p>
                                                                        <Badge className={statusColors[assignment.status]}>
                                                                            {statusLabels[assignment.status]}
                                                                        </Badge>
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-sm text-gray-500">Type</p>
                                                                        <p className="font-medium">
                                                                            {typeLabels[assignment.type]}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <div className="grid grid-cols-2 gap-4">
                                                                    <div>
                                                                        <p className="text-sm text-gray-500">Start Date</p>
                                                                        <p className="font-medium">
                                                                            {formatDate(assignment.start_date)}
                                                                        </p>
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-sm text-gray-500">Monthly Salary</p>
                                                                        <p className="font-medium">
                                                                            {assignment.monthly_salary
                                                                                ? `₦${Number(assignment.monthly_salary).toLocaleString()}`
                                                                                : 'Not set'}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                {assignment.rejection_reason && (
                                                                    <div className="bg-red-50 p-3 rounded-lg">
                                                                        <p className="text-sm text-red-600 font-medium">
                                                                            Rejection Reason
                                                                        </p>
                                                                        <p className="text-sm text-red-700">
                                                                            {assignment.rejection_reason}
                                                                        </p>
                                                                    </div>
                                                                )}
                                                                {assignment.refund_amount > 0 && (
                                                                    <div className="bg-green-50 p-3 rounded-lg">
                                                                        <p className="text-sm text-green-600 font-medium">
                                                                            Refund Processed
                                                                        </p>
                                                                        <p className="text-sm text-green-700">
                                                                            ₦{Number(assignment.refund_amount).toLocaleString()}
                                                                        </p>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </DialogContent>
                                                    </Dialog>

                                                    {['pending_acceptance', 'accepted', 'active'].includes(
                                                        assignment.status
                                                    ) && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleCancelAssignment(assignment.id)}
                                                                className="text-red-600 hover:text-red-700"
                                                            >
                                                                <XCircle className="w-4 h-4" />
                                                            </Button>
                                                        )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Overdue Alert */}
                {assignments.some((a) => a.is_overdue) && (
                    <Card className="border-orange-200 bg-orange-50">
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <AlertCircle className="w-5 h-5 text-orange-600" />
                                <div>
                                    <p className="font-medium text-orange-900">Overdue Assignments</p>
                                    <p className="text-sm text-orange-700">
                                        There are {assignments.filter((a) => a.is_overdue).length} assignments that
                                        have exceeded the employer response deadline.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AdminLayout>
    );
}
