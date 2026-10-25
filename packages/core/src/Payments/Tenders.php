<?php

declare(strict_types=1);

namespace VendaPOS\Core\Payments;

use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\Enums\TenderMethod;

/**
 * How a sale was paid, checked the same way on the till and the server
 * (approved by Daniel, M2): a completed or voided sale is paid in full,
 * exactly; a cancelled one was never paid. Mobile money moves whole
 * shillings, so any cents of a total are paid in cash.
 */
final class Tenders
{
    public const int MAX_TENDERS = 10;

    /**
     * Every way the tenders do not fit the order, as field => message.
     * Empty when they fit.
     *
     * @param  list<TenderData>  $tenders
     * @return array<string, string>
     */
    public static function problems(array $tenders, OrderStatus $status, int $totalCents): array
    {
        if ($status === OrderStatus::Cancelled) {
            return $tenders === [] ? [] : ['tenders' => 'A cancelled sale was never paid, so it has no tenders.'];
        }

        if ($tenders === []) {
            return ['tenders' => 'A completed sale needs at least one tender.'];
        }

        if (count($tenders) > self::MAX_TENDERS) {
            return ['tenders' => 'A sale takes at most '.self::MAX_TENDERS.' tenders.'];
        }

        $errors = [];
        $paid = 0;
        $cashTenders = 0;
        $payments = [];
        $references = [];

        foreach ($tenders as $index => $tender) {
            foreach (self::tenderProblems($tender) as $field => $message) {
                $errors["tenders.{$index}.{$field}"] = $message;
            }

            $paid += $tender->amount_cents;
            $cashTenders += $tender->method === TenderMethod::Cash ? 1 : 0;

            if ($tender->payment_uuid !== null) {
                if (isset($payments[$tender->payment_uuid])) {
                    $errors["tenders.{$index}.payment_uuid"] = 'This payment is already a tender of this sale.';
                }

                $payments[$tender->payment_uuid] = true;
            }

            if ($tender->mpesa_reference !== null) {
                if (isset($references[$tender->mpesa_reference])) {
                    $errors["tenders.{$index}.mpesa_reference"] = 'This M-Pesa code is already a tender of this sale.';
                }

                $references[$tender->mpesa_reference] = true;
            }
        }

        if ($cashTenders > 1) {
            $errors['tenders'] = 'A sale takes cash once; add the amounts together.';
        }

        if ($paid !== $totalCents) {
            $errors['tenders'] ??= "Tenders pay {$paid}, the sale is {$totalCents}.";
        }

        return $errors;
    }

    /**
     * The order's single payment type for reports and eTIMS: the one method
     * used, or split. Null for a sale with no tenders (cancelled).
     *
     * @param  list<TenderData>  $tenders
     */
    public static function paymentType(array $tenders): ?PaymentType
    {
        $types = array_unique(array_map(fn (TenderData $tender) => $tender->method->paymentType()->value, $tenders));

        return match (count($types)) {
            0 => null,
            1 => PaymentType::from(reset($types)),
            default => PaymentType::Split,
        };
    }

    /** @return array<string, string> */
    private static function tenderProblems(TenderData $tender): array
    {
        $errors = [];

        if ($tender->method->wholeShillingsOnly() && $tender->amount_cents % 100 !== 0) {
            $errors['amount_cents'] = 'Mobile money pays whole shillings; pay the cents in cash.';
        }

        if ($tender->method === TenderMethod::Cash) {
            if ($tender->tendered_cents === null || $tender->change_cents === null) {
                $errors['tendered_cents'] = 'Cash needs the amount handed over and the change.';
            } elseif ($tender->tendered_cents < $tender->amount_cents) {
                $errors['tendered_cents'] = 'Less cash was handed over than this tender pays.';
            } elseif ($tender->change_cents !== $tender->tendered_cents - $tender->amount_cents) {
                $expected = $tender->tendered_cents - $tender->amount_cents;
                $errors['change_cents'] = "Expected {$expected}, got {$tender->change_cents}.";
            }
        } elseif ($tender->tendered_cents !== null || $tender->change_cents !== null) {
            $errors['tendered_cents'] = 'Only cash has money handed over and change.';
        }

        if (($tender->method === TenderMethod::MpesaPush) !== ($tender->payment_uuid !== null)) {
            $errors['payment_uuid'] = 'An M-Pesa push tender, and only that, names its payment.';
        }

        if (($tender->method === TenderMethod::MpesaCode) !== ($tender->mpesa_reference !== null)) {
            $errors['mpesa_reference'] = 'A typed M-Pesa code tender, and only that, carries the code.';
        }

        return $errors;
    }
}
