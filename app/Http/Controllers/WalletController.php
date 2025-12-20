<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletDepositRequest;
use App\Http\Requests\WalletWithdrawRequest;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get user's wallet with balance and recent transactions.
     * GET /api/v1/wallet
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $wallet = $user->getOrCreateWallet();

        $transactions = $wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'wallet' => new WalletResource($wallet),
            'transactions' => WalletTransactionResource::collection($transactions),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get wallet balance only.
     * GET /api/v1/wallet/balance
     */
    public function balance(Request $request)
    {
        $user = $request->user();
        $wallet = $user->getOrCreateWallet();

        return $this->successResponse([
            'balance' => $wallet->balance,
        ]);
    }

    /**
     * Deposit money into wallet (Fake endpoint for testing).
     * POST /api/v1/wallet/deposit
     *
     * In production, this will be replaced with PayMob integration.
     */
    public function deposit(WalletDepositRequest $request)
    {
        $user = $request->user();
        $wallet = $user->getOrCreateWallet();
        $amount = $request->amount;
        $description = $request->description ?? 'Deposit via payment gateway';

        // TODO: Integrate with PayMob payment gateway
        // For now, directly add to wallet (fake deposit)

        $transaction = $wallet->deposit(
            amount: $amount,
            description: $description,
            metadata: [
                'payment_method' => 'fake_gateway',
                'note' => 'This is a test deposit. Will be replaced with PayMob integration.',
            ]
        );

        return $this->successResponse([
            'wallet' => new WalletResource($wallet),
            'transaction' => new WalletTransactionResource($transaction),
        ], 'Deposit successful.');
    }

    /**
     * Withdraw money from wallet (Fake endpoint for testing).
     * POST /api/v1/wallet/withdraw
     *
     * In production, this will be replaced with PayMob integration.
     */
    public function withdraw(WalletWithdrawRequest $request)
    {
        $user = $request->user();
        $wallet = $user->getOrCreateWallet();
        $amount = $request->amount;
        $description = $request->description ?? 'Withdrawal to bank account';

        // Check if user has bank info
        if (! $user->hasBankInfo()) {
            return $this->errorResponse(
                'Please add your bank account details before withdrawing.',
                'BANK_INFO_REQUIRED',
                400
            );
        }

        // Check sufficient balance
        if (! $wallet->hasSufficientBalance($amount)) {
            return $this->errorResponse(
                'Insufficient wallet balance.',
                'INSUFFICIENT_BALANCE',
                400
            );
        }

        // TODO: Integrate with PayMob payout API
        // For now, directly deduct from wallet (fake withdrawal)

        try {
            $transaction = $wallet->withdraw(
                amount: $amount,
                description: $description,
                metadata: [
                    'payment_method' => 'fake_gateway',
                    'bank_iban' => $user->bank_iban,
                    'bank_name' => $user->bank_name,
                    'note' => 'This is a test withdrawal. Will be replaced with PayMob integration.',
                ]
            );

            return $this->successResponse([
                'wallet' => new WalletResource($wallet),
                'transaction' => new WalletTransactionResource($transaction),
            ], 'Withdrawal successful.');
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                'WITHDRAWAL_FAILED',
                400
            );
        }
    }

    /**
     * Get wallet transactions history.
     * GET /api/v1/wallet/transactions
     */
    public function transactions(Request $request)
    {
        $user = $request->user();
        $wallet = $user->getOrCreateWallet();

        $query = $wallet->transactions()->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $transactions = $query->paginate($request->input('per_page', 20));

        return $this->successResponse([
            'transactions' => WalletTransactionResource::collection($transactions),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
