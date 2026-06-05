import typer
import json
import sys
from ..client.api_client import ApiClient, ApiClientError

app = typer.Typer(help="Manage wallet and payments")

def _format_wallet_output(wallet: dict, json_output: bool = False) -> str:
    """Format wallet data for display."""
    if json_output:
        return json.dumps(wallet, indent=2)
    
    lines = []
    lines.append("--- Wallet Information ---")
    
    if wallet.get('user_id'):
        lines.append(f"User ID: {wallet['user_id']}")
    
    if wallet.get('balance') is not None:
        lines.append(f"Balance: ₦{wallet['balance']:,.2f}")
    
    if wallet.get('currency'):
        lines.append(f"Currency: {wallet['currency']}")
    
    if wallet.get('pending_withdrawals') is not None:
        lines.append(f"Pending Withdrawals: ₦{wallet['pending_withdrawals']:,.2f}")
    
    if wallet.get('available_balance') is not None:
        lines.append(f"Available Balance: ₦{wallet['available_balance']:,.2f}")
    
    return "\n".join(lines)


def _format_transaction_output(transaction: dict) -> str:
    """Format transaction data for display."""
    lines = []
    lines.append(f"Transaction #{transaction.get('id', 'N/A')}")
    
    if transaction.get('type'):
        lines.append(f"  Type: {transaction['type']}")
    
    if transaction.get('amount') is not None:
        lines.append(f"  Amount: ₦{transaction['amount']:,.2f}")
    
    if transaction.get('status'):
        status_icon = "✓" if transaction['status'] == 'completed' else "○"
        lines.append(f"  Status: {status_icon} {transaction['status']}")
    
    if transaction.get('reference'):
        lines.append(f"  Reference: {transaction['reference']}")
    
    if transaction.get('description'):
        lines.append(f"  Description: {transaction['description']}")
    
    if transaction.get('created_at'):
        lines.append(f"  Date: {transaction['created_at']}")
    
    return "\n".join(lines)


@app.command("balance")
def get_wallet(
    ctx: typer.Context,
):
    """Get current wallet balance and information."""
    try:
        client = ApiClient()
        response = client.get_wallet()
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            # Extract wallet data from response
            wallet = response.get('data', response)
            typer.echo(_format_wallet_output(wallet, json_output=False))
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("transactions")
def get_transactions(
    ctx: typer.Context,
    limit: int = typer.Option(20, "--limit", help="Number of transactions to return"),
    offset: int = typer.Option(0, "--offset", help="Offset for pagination"),
):
    """Get wallet transaction history."""
    try:
        client = ApiClient()
        response = client.get_wallet_transactions(limit=limit, offset=offset)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            transactions = response.get('data', response.get('transactions', []))
            
            if not transactions:
                typer.echo("No transactions found.")
                return
            
            typer.echo(f"Transaction History ({len(transactions)} transactions):\n")
            
            for tx in transactions:
                typer.echo(_format_transaction_output(tx))
                typer.echo("")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("deposit")
def deposit(
    ctx: typer.Context,
    amount: float = typer.Argument(..., help="Amount to deposit"),
    method: str = typer.Option("card", "--method", "-m", help="Payment method (card, bank_transfer, ussd)"),
):
    """Initiate a wallet deposit."""
    try:
        client = ApiClient()
        response = client.deposit(amount=amount, method=method)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'Deposit initiated.')
            data = response.get('data', {})
            
            typer.echo(message)
            
            if data.get('payment_url'):
                typer.echo(f"Payment URL: {data['payment_url']}")
            if data.get('reference'):
                typer.echo(f"Reference: {data['reference']}")
            if data.get('amount'):
                typer.echo(f"Amount: ₦{data['amount']:,.2f}")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("withdraw")
def withdraw(
    ctx: typer.Context,
    amount: float = typer.Argument(..., help="Amount to withdraw"),
):
    """Request a withdrawal from wallet."""
    try:
        client = ApiClient()
        response = client.withdraw(amount=amount)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'Withdrawal request submitted.')
            data = response.get('data', {})
            
            typer.echo(message)
            
            if data.get('reference'):
                typer.echo(f"Reference: {data['reference']}")
            if data.get('amount'):
                typer.echo(f"Amount: ₦{data['amount']:,.2f}")
            if data.get('status'):
                typer.echo(f"Status: {data['status']}")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("pending-withdrawals")
def get_pending_withdrawals(
    ctx: typer.Context,
):
    """Get pending withdrawal requests."""
    try:
        client = ApiClient()
        response = client.get_pending_withdrawals()
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            withdrawals = response.get('data', [])
            
            if not withdrawals:
                typer.echo("No pending withdrawals.")
                return
            
            typer.echo(f"Pending Withdrawals ({len(withdrawals)}):\n")
            
            for w in withdrawals:
                typer.echo(f"--- Withdrawal #{w.get('id', 'N/A')} ---")
                if w.get('user_id'):
                    typer.echo(f"  User ID: {w['user_id']}")
                if w.get('amount') is not None:
                    typer.echo(f"  Amount: ₦{w['amount']:,.2f}")
                if w.get('status'):
                    typer.echo(f"  Status: {w['status']}")
                if w.get('created_at'):
                    typer.echo(f"  Requested: {w['created_at']}")
                typer.echo("")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)