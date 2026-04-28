import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Separator } from '@/Components/ui/separator';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/Components/ui/dialog';
import { Textarea } from '@/Components/ui/textarea';
import {
    CheckCircle,
    XCircle,
    Clock,
    User,
    MapPin,
    Briefcase,
    Calendar,
    AlertCircle,
    Phone,
    Mail,
    Star,
    Shield,
} from 'lucide-react';

export default function AssignmentAcceptance({ auth, assignment, maid, stats }) {
    const [loading, setLoading] = useState(false);
    const [showRejectDialog, setShowRejectDialog] = useState(false);
    const [rejectionReason, setRejectionReason] = useState('');

    const handleAccept = async () => {
        setLoading(true);
        try {
            const response = await fetch(`/employer/assignments/${assignment.id}/accept`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            });

            const data = await response.json();

            if (response.ok) {
                toast.success('Assignment accepted successfully!');
                router.visit('/employer/dashboard');
            } else {
                toast.error(data.message || 'Failed to accept assignment');
            }
        } catch (error) {
            toast.error('An error occurred. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const handleReject = async () => {
        if (!rejectionReason.trim()) {
            toast.error('Please provide a reason for rejection');
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(`/employer/assignments/${assignment.id}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ reason: rejectionReason }),
            });

            const data = await response.json();

            if (response.ok) {
                toast.success('Assignment rejected. A refund has been processed to your wallet.');
                setShowRejectDialog(false);
                router.visit('/employer/dashboard');
            } else {
                toast.error(data.message || 'Failed to reject assignment');
            }
        } catch (error) {
            toast.error('An error occurred. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'long',
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

    const getTimeRemaining = () => {
        if (!assignment.employer_response_deadline) return null;
        const deadline = new Date(assignment.employer_response_deadline);
        const now = new Date();
        const diff = deadline - now;

        if (diff <= 0) return { expired: true, text: 'Expired' };

        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

        return {
            expired: false,
            text: `${hours}h ${minutes}m remaining`,
            hours,
            minutes,
        };
    };

    const timeRemaining = getTimeRemaining();

    // If assignment is not pending acceptance
    if (assignment.status !== 'pending_acceptance') {
        return (
            <EmployerLayout auth={auth}>
                <Head title="Assignment Status" />
                <div className="p-6 max-w-4xl mx-auto">
                    <Card>
                        <CardContent className="p-8 text-center">
                            <div className="mb-4">
                                {assignment.status === 'accepted' || assignment.status === 'active' ? (
                                    <CheckCircle className="w-16 h-16 text-green-500 mx-auto" />
                                ) : (
                                    <XCircle className="w-16 h-16 text-red-500 mx-auto" />
                                )}
                            </div>
                            <h2 className="text-2xl font-bold text-gray-900 mb-2">
                                {assignment.status === 'accepted' || assignment.status === 'active'
                                    ? 'Assignment Already Accepted'
                                    : 'Assignment No Longer Available'}
                            </h2>
                            <p className="text-gray-500 mb-6">
                                {assignment.status === 'accepted' || assignment.status === 'active'
                                    ? 'You have already accepted this assignment. The maid has been notified.'
                                    : 'This assignment has been ' + assignment.status + '.'}
                            </p>
                            <Button onClick={() => router.visit('/employer/dashboard')}>
                                Go to Dashboard
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </EmployerLayout>
        );
    }

    return (
        <EmployerLayout auth={auth}>
            <Head title="Review Assignment" />

            <div className="p-6 max-w-6xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <div className="flex items-center gap-2 mb-2">
                        <Badge variant="warning" className="text-yellow-800 bg-yellow-100">
                            <Clock className="w-3 h-3 mr-1" />
                            Pending Your Response
                        </Badge>
                        {timeRemaining && (
                            <Badge
                                variant={timeRemaining.expired ? 'destructive' : 'outline'}
                                className={
                                    timeRemaining.hours < 12 ? 'text-red-600 border-red-200' : ''
                                }
                            >
                                <Clock className="w-3 h-3 mr-1" />
                                {timeRemaining.text}
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900">Review Your Maid Assignment</h1>
                    <p className="text-gray-500 mt-1">
                        Please review the maid assigned to you and accept or reject within 48 hours
                    </p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Maid Profile Card */}
                    <div className="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="w-5 h-5" />
                                    Maid Profile
                                </CardTitle>
                                <CardDescription>
                                    Details of the maid assigned to you
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-start gap-6">
                                    <Avatar className="w-24 h-24">
                                        <AvatarImage
                                            src={maid?.profile_photo_url}
                                            alt={maid?.first_name}
                                        />
                                        <AvatarFallback className="text-2xl">
                                            {maid?.first_name?.[0]}{maid?.last_name?.[0]}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2 mb-1">
                                            <h2 className="text-xl font-bold text-gray-900">
                                                {maid?.first_name} {maid?.last_name}
                                            </h2>
                                            {maid?.is_verified && (
                                                <Badge variant="success" className="text-xs">
                                                    <Shield className="w-3 h-3 mr-1" />
                                                    Verified
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-gray-500 mb-4">{maid?.title || 'Professional Maid'}</p>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="flex items-center gap-2 text-sm">
                                                <MapPin className="w-4 h-4 text-gray-400" />
                                                <span>{maid?.location || 'Location not specified'}</span>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm">
                                                <Briefcase className="w-4 h-4 text-gray-400" />
                                                <span>{maid?.experience_years || 0} years experience</span>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm">
                                                <Calendar className="w-4 h-4 text-gray-400" />
                                                <span>Age: {maid?.age || 'N/A'}</span>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm">
                                                <Star className="w-4 h-4 text-yellow-400" />
                                                <span>{maid?.rating || 'New'} rating</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <Separator className="my-6" />

                                {/* Skills */}
                                <div className="mb-6">
                                    <h3 className="font-semibold text-gray-900 mb-3">Skills</h3>
                                    <div className="flex flex-wrap gap-2">
                                        {maid?.skills?.map((skill, index) => (
                                            <Badge key={index} variant="secondary">
                                                {skill.name}
                                            </Badge>
                                        )) || (
                                                <span className="text-gray-500 text-sm">No skills listed</span>
                                            )}
                                    </div>
                                </div>

                                {/* Bio */}
                                {maid?.bio && (
                                    <div className="mb-6">
                                        <h3 className="font-semibold text-gray-900 mb-2">About</h3>
                                        <p className="text-gray-600 text-sm leading-relaxed">
                                            {maid.bio}
                                        </p>
                                    </div>
                                )}

                                {/* Contact Info */}
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <h3 className="font-semibold text-gray-900 mb-3">Contact Information</h3>
                                    <div className="space-y-2 text-sm">
                                        <div className="flex items-center gap-2">
                                            <Phone className="w-4 h-4 text-gray-400" />
                                            <span>{maid?.phone || 'Not available'}</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Mail className="w-4 h-4 text-gray-400" />
                                            <span>{maid?.email || 'Not available'}</span>
                                        </div>
                                    </div>
                                    <p className="text-xs text-gray-500 mt-3">
                                        * Contact details will be fully available after acceptance
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Assignment Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Assignment Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-6">
                                    <div>
                                        <p className="text-sm text-gray-500 mb-1">Start Date</p>
                                        <p className="font-medium text-gray-900">
                                            {formatDate(assignment.start_date)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-500 mb-1">Monthly Salary</p>
                                        <p className="font-medium text-gray-900">
                                            {assignment.monthly_salary
                                                ? `₦${Number(assignment.monthly_salary).toLocaleString()}`
                                                : 'To be discussed'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-500 mb-1">Assigned By</p>
                                        <p className="font-medium text-gray-900 capitalize">
                                            {assignment.assigned_by_type === 'ai' ? 'AI Agent' : 'Admin'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-500 mb-1">Response Deadline</p>
                                        <p className="font-medium text-gray-900">
                                            {formatDateTime(assignment.employer_response_deadline)}
                                        </p>
                                    </div>
                                </div>

                                {assignment.notes && (
                                    <div className="mt-6 bg-blue-50 p-4 rounded-lg">
                                        <p className="text-sm text-blue-800">
                                            <span className="font-medium">Note: </span>
                                            {assignment.notes}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Action Card */}
                    <div className="space-y-6">
                        <Card className="border-yellow-200 bg-yellow-50">
                            <CardHeader>
                                <CardTitle className="text-yellow-900 flex items-center gap-2">
                                    <AlertCircle className="w-5 h-5" />
                                    Action Required
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-yellow-800">
                                    Please review the maid profile above and make your decision.
                                    You have until{' '}
                                    <strong>{formatDateTime(assignment.employer_response_deadline)}</strong>{' '}
                                    to respond.
                                </p>

                                <div className="space-y-3">
                                    <Button
                                        className="w-full"
                                        size="lg"
                                        onClick={handleAccept}
                                        disabled={loading || timeRemaining?.expired}
                                    >
                                        <CheckCircle className="w-4 h-4 mr-2" />
                                        {loading ? 'Processing...' : 'Accept Assignment'}
                                    </Button>

                                    <Button
                                        className="w-full"
                                        variant="outline"
                                        size="lg"
                                        onClick={() => setShowRejectDialog(true)}
                                        disabled={loading || timeRemaining?.expired}
                                    >
                                        <XCircle className="w-4 h-4 mr-2" />
                                        Reject Assignment
                                    </Button>
                                </div>

                                {timeRemaining?.expired && (
                                    <div className="bg-red-100 text-red-800 p-3 rounded-lg text-sm">
                                        This assignment has expired. Please contact support if you still want to proceed.
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Info Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">What happens next?</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm text-gray-600">
                                <div className="flex gap-2">
                                    <CheckCircle className="w-4 h-4 text-green-500 mt-0.5" />
                                    <span>If you accept, the maid will be notified immediately</span>
                                </div>
                                <div className="flex gap-2">
                                    <CheckCircle className="w-4 h-4 text-green-500 mt-0.5" />
                                    <span>You can contact the maid to arrange start details</span>
                                </div>
                                <div className="flex gap-2">
                                    <CheckCircle className="w-4 h-4 text-green-500 mt-0.5" />
                                    <span>If you reject, a refund will be processed to your wallet</span>
                                </div>
                                <div className="flex gap-2">
                                    <CheckCircle className="w-4 h-4 text-green-500 mt-0.5" />
                                    <span>We will search for a replacement match automatically</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Rejection Dialog */}
                <Dialog open={showRejectDialog} onOpenChange={setShowRejectDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Reject Assignment</DialogTitle>
                            <DialogDescription>
                                Please tell us why you are rejecting this assignment. This helps us find a better match for you.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-4">
                            <Textarea
                                placeholder="Enter your reason for rejection..."
                                value={rejectionReason}
                                onChange={(e) => setRejectionReason(e.target.value)}
                                rows={4}
                            />
                            <div className="bg-blue-50 p-3 rounded-lg text-sm text-blue-800">
                                <p>
                                    <strong>Note:</strong> A refund of ₦50,000 will be processed to your wallet,
                                    and we will automatically search for a replacement maid matching your requirements.
                                </p>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setShowRejectDialog(false)}>
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleReject}
                                disabled={loading || !rejectionReason.trim()}
                            >
                                {loading ? 'Processing...' : 'Confirm Rejection'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </EmployerLayout>
    );
}
