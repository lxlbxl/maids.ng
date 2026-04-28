import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
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
    DialogFooter,
} from '@/Components/ui/dialog';
import { toast } from 'sonner';
import {
    Wallet,
    ArrowDownLeft,
    ArrowUpRight,
    History,
    Banknote,
    RefreshCw,
    AlertCircle,
    CheckCircle,
    Clock,
    TrendingUp,
} from 'lucide-react';

export default function MaidWallet({ auth, wallet, transactions, earnings }) {
    const [loading, setLoading] = useState(false);
    const [withdrawalAmount, setWithdrawalAmount] = useState('');
    const [showWithdrawalDialog, setShowWithdrawalDialog] = useState(false);

    const handleWithdrawalRequest = async () => {
        if (!withdrawalAmount || parseFloat(withdrawalAmount) <= 0) {
            toast.error('Please enter a valid amount');
            return;
        }

        if (parseFloat(withdrawalAmount) > wallet?.available_balance) {
            toast.error('Insufficient balance');
            return;
        }

        setLoading(true);
        try {
            const response = await fetch('/maid/wallet/withdraw', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ amount: parseFloat(withdrawalAmount) }),
            });

            const data = await response.json();

            if (response.ok) {
                toast.success('Withdrawal request submitted successfully');
                setShowWithdrawalDialog(false);
                setWithdrawalAmount('');
                // Refresh page to show updated data
                window.location.reload();
            } else {
                toast.error(data.message || 'Failed to submit withdrawal request');
            }
        } catch (error) {
            toast.error('An error occurred');
        } finally {
            setLoading(false);
        }
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: wallet?.currency || 'NGN',
        }).format(amount || 0);
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getTransactionIcon = (type) => {
        switch (type) {
            case 'credit':
            case 'salary_earned':
                return <ArrowDownLeft className="w-4 h-4 text-green-600" />;
            case 'debit':
            case 'withdrawal':
                return <ArrowUpRight className="w-4 h-4 text-red-600" />;
            case 'withdrawal_pending':
                return <Clock className="w-4 h-4 text-yellow-600" />;
            default:
                return <History className="w-4 h-4 text-gray-600" />;
        }
    };

    const getTransactionColor = (type) => {
        switch (type) {
            case 'credit':
            case 'salary_earned':
                return 'text-green-600';
            case 'debit':
            case 'withdrawal':
                return 'text-red-600';
            default:
                return 'text-gray-600';
        }
    };

    return (
        <MaidLayout auth={auth}>
            <Head title="My Earnings" />

            <div className="p-6 space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">My Earnings</h1>
                    <p className="text-gray-500 mt-1">View your earnings and manage withdrawals</p>
                </div>

                {/* Balance Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="bg-gradient-to-br from-green-600 to-green-700 text-white">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-green-100 text-sm">Available Balance</p>
                                    <p className="text-3xl font-bold mt-1">
                                        {formatCurrency(wallet?.available_balance)}
                                    </p>
                                </div>
                                <Wallet className="w-10 h-10 text-green-200" />
                            </div>
                            <div className="mt-4">
                                <Dialog open={showWithdrawalDialog} onOpenChange={setShowWithdrawalDialog}>
                                    <DialogTrigger asChild>
                                        <Button variant="secondary" size="sm" className="w-full">
                                            <Banknote className="w-4 h-4 mr-2" />
                                            Request Withdrawal
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Request Withdrawal</DialogTitle>
                                            <DialogDescription>
                                                Request a withdrawal from your available balance
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="space-y-4 py-4">
                                            <div>
                                                <Label htmlFor="amount">Amount (NGN)</Label>
                                                <Input
                                                    id="amount"
                                                    type="number"
                                                    placeholder="Enter amount"
                                                    value={withdrawalAmount}
                                                    onChange={(e) => setWithdrawalAmount(e.target.value)}
                                                    min="1000"
                                                    step="100"
                                                    max={wallet?.available_balance}
                                                />
                                                <p className="text-sm text-gray-500 mt-1">
                                                    Available: {formatCurrency(wallet?.available_balance)}
                                                </p>
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                variant="outline"
                                                onClick={() => setShowWithdrawalDialog(false)}
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                onClick={handleWithdrawalRequest}
                                                disabled={
                                                    loading ||
                                                    !withdrawalAmount ||
                                                    parseFloat(withdrawalAmount) > wallet?.available_balance
                                                }
                                            >
                                                {loading ? 'Processing...' : 'Submit Request'}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">Total Balance</p>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {formatCurrency(wallet?.balance)}
                                    </p>
                                </div>
                                <Wallet className="w-8 h-8 text-gray-400" />
                            </div>
                            <p className="text-sm text-gray-500 mt-2">
                                Including pending withdrawals
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">Pending Withdrawal</p>
                                    <p className="text-2xl font-bold text-yellow-600">
                                        {formatCurrency(wallet?.pending_withdrawal)}
                                    </p>
                                </div>
                                <Clock className="w-8 h-8 text-yellow-500" />
                            </div>
                            <p className="text-sm text-gray-500 mt-2">
                                Awaiting admin approval
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Earnings Summary */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">Total Earned</p>
                                    <p className="text-2xl font-bold text-green-600">
                                        {formatCurrency(wallet?.total_earned)}
                                    </p>
                                </div>
                                <TrendingUp className="w-8 h-8 text-green-500" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">Total Withdrawn</p>
                                    <p className="text-2xl font-bold text-blue-600">
                                        {formatCurrency(wallet?.total_withdrawn)}
                                    </p>
                                </div>
                                <Banknote className="w-8 h-8 text-blue-500" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Current Assignment Earnings */}
                {earnings && earnings.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp className="w-5 h-5" />
                                Current Assignment Earnings
                            </CardTitle>
                            <CardDescription>
                                Earnings from your active assignments
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {earnings.map((earning, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center justify-between p-4 bg-gray-50 rounded-lg"
                                    >
                                        <div>
                                            <p className="font-medium text-gray-900">
                                                {earning.employer_name}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                Started: {new Date(earning.start_date).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-bold text-gray-900">
                                                {formatCurrency(earning.monthly_salary)}/month
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                Next payment: {new Date(earning.next_due_date).toLocaleDateString()}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Transaction History */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <History className="w-5 h-5" />
                                Transaction History
                            </CardTitle>
                            <Button variant="outline" size="sm">
                                <RefreshCw className="w-4 h-4 mr-2" />
                                Refresh
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions?.data?.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center py-8 text-gray-500">
                                            No transactions yet
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    transactions?.data?.map((transaction) => (
                                        <TableRow key={transaction.id}>
                                            <TableCell className="whitespace-nowrap">
                                                {formatDate(transaction.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {getTransactionIcon(transaction.type)}
                                                    <span className="capitalize">
                                                        {transaction.type.replace('_', ' ')}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>{transaction.description}</TableCell>
                                            <TableCell
                                                className={`text-right font-medium ${getTransactionColor(
                                                    transaction.type
                                                )}`}
                                            >
                                                {['credit', 'salary_earned'].includes(transaction.type)
                                                    ? '+'
                                                    : '-'}
                                                {formatCurrency(transaction.amount)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        transaction.status === 'completed'
                                                            ? 'success'
                                                            : transaction.status === 'pending'
                                                                ? 'warning'
                                                                : transaction.status === 'approved'
                                                                    ? 'success'
                                                                    : 'default'
                                                    }
                                                >
                                                    {transaction.status}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Withdrawal Info */}
                <Card className="bg-blue-50 border-blue-200">
                    <CardContent className="p-4">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="w-5 h-5 text-blue-600 mt-0.5" />
                            <div>
                                <p className="font-medium text-blue-900">Withdrawal Information</p>
                                <p className="text-sm text-blue-700 mt-1">
                                    Withdrawal requests are processed within 24-48 hours.
                                    You will receive an SMS notification once your withdrawal has been approved and processed.
                                    Minimum withdrawal amount is ₦1,000.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </MaidLayout>
    );
}
