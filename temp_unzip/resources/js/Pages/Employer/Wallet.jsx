import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';
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
    ArrowUpRight,
    ArrowDownLeft,
    History,
    CreditCard,
    Shield,
    RefreshCw,
    AlertCircle,
    CheckCircle,
} from 'lucide-react';

export default function EmployerWallet({ auth, wallet, transactions, upcomingPayments }) {
    const [loading, setLoading] = useState(false);
    const [depositAmount, setDepositAmount] = useState('');
    const [showDepositDialog, setShowDepositDialog] = useState(false);

    const handleDeposit = async () => {
        if (!depositAmount || parseFloat(depositAmount) <= 0) {
            toast.error('Please enter a valid amount');
            return;
        }

        setLoading(true);
        try {
            const response = await fetch('/employer/wallet/deposit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ amount: parseFloat(depositAmount) }),
            });

            const data = await response.json();

            if (response.ok) {
                toast.success('Deposit initiated. Please complete payment.');
                if (data.payment_url) {
                    window.location.href = data.payment_url;
                }
                setShowDepositDialog(false);
                setDepositAmount('');
            } else {
                toast.error(data.message || 'Failed to initiate deposit');
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
            case 'refund':
                return <ArrowDownLeft className="w-4 h-4 text-green-600" />;
            case 'debit':
            case 'payment':
                return <ArrowUpRight className="w-4 h-4 text-red-600" />;
            case 'escrow_hold':
            case 'escrow_release':
                return <Shield className="w-4 h-4 text-blue-600" />;
            default:
                return <History className="w-4 h-4 text-gray-600" />;
        }
    };

    const getTransactionColor = (type) => {
        switch (type) {
            case 'credit':
            case 'refund':
                return 'text-green-600';
            case 'debit':
            case 'payment':
                return 'text-red-600';
            default:
                return 'text-gray-600';
        }
    };

    return (
        <EmployerLayout auth={auth}>
            <Head title="My Wallet" />

            <div className="p-6 space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">My Wallet</h1>
                    <p className="text-gray-500 mt-1">Manage your funds and view transaction history</p>
                </div>

                {/* Balance Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="bg-gradient-to-br from-blue-600 to-blue-700 text-white">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-blue-100 text-sm">Available Balance</p>
                                    <p className="text-3xl font-bold mt-1">
                                        {formatCurrency(wallet?.available_balance)}
                                    </p>
                                </div>
                                <Wallet className="w-10 h-10 text-blue-200" />
                            </div>
                            <div className="mt-4 flex gap-2">
                                <Dialog open={showDepositDialog} onOpenChange={setShowDepositDialog}>
                                    <DialogTrigger asChild>
                                        <Button variant="secondary" size="sm" className="flex-1">
                                            <CreditCard className="w-4 h-4 mr-2" />
                                            Deposit
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Deposit Funds</DialogTitle>
                                            <DialogDescription>
                                                Add funds to your wallet for maid hiring and salary payments
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="space-y-4 py-4">
                                            <div>
                                                <Label htmlFor="amount">Amount (NGN)</Label>
                                                <Input
                                                    id="amount"
                                                    type="number"
                                                    placeholder="Enter amount"
                                                    value={depositAmount}
                                                    onChange={(e) => setDepositAmount(e.target.value)}
                                                    min="1000"
                                                    step="100"
                                                />
                                                <p className="text-sm text-gray-500 mt-1">
                                                    Minimum deposit: ₦1,000
                                                </p>
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                variant="outline"
                                                onClick={() => setShowDepositDialog(false)}
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                onClick={handleDeposit}
                                                disabled={loading || !depositAmount}
                                            >
                                                {loading ? 'Processing...' : 'Proceed to Payment'}
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
                                Including escrow funds
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500">In Escrow</p>
                                    <p className="text-2xl font-bold text-blue-600">
                                        {formatCurrency(wallet?.escrow_balance)}
                                    </p>
                                </div>
                                <Shield className="w-8 h-8 text-blue-500" />
                            </div>
                            <p className="text-sm text-gray-500 mt-2">
                                Held for pending transactions
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Upcoming Payments */}
                {upcomingPayments && upcomingPayments.length > 0 && (
                    <Card className="border-yellow-200 bg-yellow-50">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-yellow-900">
                                <AlertCircle className="w-5 h-5" />
                                Upcoming Salary Payments
                            </CardTitle>
                            <CardDescription className="text-yellow-700">
                                Ensure you have sufficient balance for these upcoming payments
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {upcomingPayments.map((payment, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center justify-between p-3 bg-white rounded-lg"
                                    >
                                        <div>
                                            <p className="font-medium text-gray-900">
                                                {payment.maid_name}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                Due in {payment.days_until_due} days
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-bold text-gray-900">
                                                {formatCurrency(payment.monthly_salary)}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                {new Date(payment.next_due_date).toLocaleDateString()}
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
                                    <TableHead>Reference</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions?.data?.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center py-8 text-gray-500">
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
                                            <TableCell className="text-sm text-gray-500">
                                                {transaction.reference_type
                                                    ? `${transaction.reference_type} #${transaction.reference_id}`
                                                    : 'N/A'}
                                            </TableCell>
                                            <TableCell
                                                className={`text-right font-medium ${getTransactionColor(
                                                    transaction.type
                                                )}`}
                                            >
                                                {['credit', 'refund'].includes(transaction.type)
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

                {/* Wallet Stats */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-gray-500">Total Deposited</p>
                            <p className="text-xl font-bold text-green-600">
                                {formatCurrency(wallet?.total_deposited)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-gray-500">Total Spent</p>
                            <p className="text-xl font-bold text-red-600">
                                {formatCurrency(wallet?.total_spent)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-gray-500">Total Refunded</p>
                            <p className="text-xl font-bold text-blue-600">
                                {formatCurrency(wallet?.total_refunded)}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </EmployerLayout>
    );
}
