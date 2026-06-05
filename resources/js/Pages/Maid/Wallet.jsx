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
    Clock,
    TrendingUp,
} from 'lucide-react';

export default function MaidWallet({ auth, wallet, transactions, earnings }) {
    const [loading, setLoading] = useState(false);
    const [withdrawalAmount, setWithdrawalAmount] = useState('');
    const [showWithdrawalDialog, setShowWithdrawalDialog] = useState(false);

    const handleWithdrawalRequest = async () => {
        if (!withdrawalAmount || parseFloat(withdrawalAmount) <= 0) {
            toast.error('Please enter the amount you want to withdraw');
            return;
        }

        if (parseFloat(withdrawalAmount) > wallet?.available_balance) {
            toast.error('You do not have enough money for this withdrawal');
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
                toast.success('Your withdrawal request has been sent! We will process it soon.');
                setShowWithdrawalDialog(false);
                setWithdrawalAmount('');
                window.location.reload();
            } else {
                toast.error(data.message || 'Something went wrong. Please try again.');
            }
        } catch (error) {
            toast.error('Could not connect. Please check your internet and try again.');
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
        if (!dateString) return 'Not available';
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

    const getTransactionLabel = (type) => {
        switch (type) {
            case 'credit': return 'Money Received';
            case 'salary_earned': return 'Salary Paid';
            case 'debit': return 'Money Sent Out';
            case 'withdrawal': return 'Withdrawal';
            case 'withdrawal_pending': return 'Withdrawal Waiting';
            default: return type?.replace(/_/g, ' ');
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

    const getStatusLabel = (status) => {
        switch (status) {
            case 'completed': return '✅ Done';
            case 'pending': return '⏳ Waiting';
            case 'approved': return '✔️ Approved';
            case 'failed': return '❌ Failed';
            default: return status;
        }
    };

    return (
        <MaidLayout auth={auth}>
            <Head title="My Wallet | Maids.ng" />

            <div className="p-6 space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">My Wallet</h1>
                    <p className="text-gray-500 mt-1">See your money balance and send a request to withdraw to your bank</p>
                </div>

                {/* Balance Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {/* Available Balance - Main Card */}
                    <Card className="bg-gradient-to-br from-green-600 to-green-700 text-white">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-green-100 text-sm">Money I Can Take Out Now</p>
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
                                            Send Money to My Bank
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Request Withdrawal</DialogTitle>
                                            <DialogDescription>
                                                Enter the amount you want to send to your bank account. The money will arrive within 1–2 working days.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="space-y-4 py-4">
                                            <div>
                                                <Label htmlFor="amount">How much do you want? (₦)</Label>
                                                <Input
                                                    id="amount"
                                                    type="number"
                                                    placeholder="e.g. 5000"
                                                    value={withdrawalAmount}
                                                    onChange={(e) => setWithdrawalAmount(e.target.value)}
                                                    min="1000"
                                                    step="100"
                                                    max={wallet?.available_balance}
                                                />
                                                <p className="text-sm text-gray-500 mt-1">
                                                    You have {formatCurrency(wallet?.available_balance)} available. Minimum is ₦1,000.
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
                                                {loading ? 'Sending request...' : 'Yes, Send to My Bank'}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Total Balance */}
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">My Total Balance</p>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {formatCurrency(wallet?.balance)}
                                    </p>
                                </div>
                                <Wallet className="w-8 h-8 text-gray-400" />
                            </div>
                            <p className="text-sm text-gray-500 mt-2">
                                Includes money that is still being processed
                            </p>
                        </CardContent>
                    </Card>

                    {/* Pending Withdrawal */}
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">Withdrawal Being Processed</p>
                                    <p className="text-2xl font-bold text-yellow-600">
                                        {formatCurrency(wallet?.pending_withdrawal)}
                                    </p>
                                </div>
                                <Clock className="w-8 h-8 text-yellow-500" />
                            </div>
                            <p className="text-sm text-gray-500 mt-2">
                                Waiting to be sent to your bank
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
                                    <p className="text-sm font-medium text-gray-500">All Money I Have Earned</p>
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
                                    <p className="text-sm font-medium text-gray-500">Total Money Withdrawn</p>
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
                                My Current Job Pay
                            </CardTitle>
                            <CardDescription>
                                How much you are earning from your current job(s)
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
                                                Working for: {earning.employer_name}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                Started: {new Date(earning.start_date).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' })}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-bold text-gray-900">
                                                {formatCurrency(earning.monthly_salary)} / month
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                Next pay: {new Date(earning.next_due_date).toLocaleDateString('en-NG', { day: 'numeric', month: 'short' })}
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
                                Money History
                            </CardTitle>
                            <Button variant="outline" size="sm" onClick={() => window.location.reload()}>
                                <RefreshCw className="w-4 h-4 mr-2" />
                                Refresh
                            </Button>
                        </div>
                        <CardDescription>Every time money came in or went out of your wallet</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date & Time</TableHead>
                                    <TableHead>What Happened</TableHead>
                                    <TableHead>Details</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions?.data?.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center py-8 text-gray-500">
                                            No transactions yet. When you receive money it will appear here.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    transactions?.data?.map((transaction) => (
                                        <TableRow key={transaction.id}>
                                            <TableCell className="whitespace-nowrap text-sm">
                                                {formatDate(transaction.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {getTransactionIcon(transaction.transaction_type || transaction.type)}
                                                    <span className="text-sm">
                                                        {getTransactionLabel(transaction.transaction_type || transaction.type)}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-gray-600">{transaction.description || '—'}</TableCell>
                                            <TableCell
                                                className={`text-right font-medium ${getTransactionColor(
                                                    transaction.transaction_type || transaction.type
                                                )}`}
                                            >
                                                {['credit', 'salary_earned'].includes(transaction.transaction_type || transaction.type)
                                                    ? '+'
                                                    : '-'}
                                                {formatCurrency(transaction.amount)}
                                            </TableCell>
                                            <TableCell>
                                                <span className={`px-2 py-1 rounded-full text-[11px] font-medium ${
                                                    transaction.status === 'completed' || transaction.status === 'approved'
                                                        ? 'bg-green-100 text-green-700'
                                                        : transaction.status === 'pending'
                                                            ? 'bg-yellow-100 text-yellow-700'
                                                            : 'bg-gray-100 text-gray-600'
                                                }`}>
                                                    {getStatusLabel(transaction.status)}
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Important Info Box */}
                <Card className="bg-blue-50 border-blue-200">
                    <CardContent className="p-4">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <p className="font-medium text-blue-900">Important: How Withdrawals Work</p>
                                <ul className="text-sm text-blue-700 mt-2 space-y-1 list-disc list-inside">
                                    <li>You can only withdraw money that is "available" (shown in green above).</li>
                                    <li>The minimum amount you can withdraw at once is <strong>₦1,000</strong>.</li>
                                    <li>Your money will reach your bank within <strong>1 to 2 working days</strong>.</li>
                                    <li>You will receive an SMS on your phone when it has been sent.</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </MaidLayout>
    );
}
