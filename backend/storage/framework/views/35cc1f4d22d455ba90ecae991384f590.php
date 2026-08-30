<!doctype html>
<html><head><meta charset="utf-8"><style>
@page { margin: 34px 42px; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #17202a; }
h1 { font-size: 20px; margin: 0 0 4px; color: #123b5d; }
h2 { font-size: 12px; margin: 18px 0 7px; color: #123b5d; border-bottom: 1px solid #b8c7d1; padding-bottom: 4px; }
.meta, .items, .totals { width: 100%; border-collapse: collapse; }
.meta td { padding: 4px 6px; vertical-align: top; }
.items th { background: #123b5d; color: white; padding: 7px 5px; text-align: left; }
.items td { border-bottom: 1px solid #dbe3e8; padding: 6px 5px; }
.num { text-align: right; }
.totals { width: 48%; margin-left: 52%; margin-top: 12px; }
.totals td { padding: 5px 7px; border-bottom: 1px solid #dbe3e8; }
.total { font-weight: bold; background: #e9f1f6; }
.footer { position: fixed; bottom: -16px; left: 0; right: 0; font-size: 8px; color: #69757d; text-align: center; }
</style></head><body>
<h1>Bill Certificate</h1>
<div>Generated from Kuwalee SiteFlow</div>
<h2>Bill details</h2>
<table class="meta">
<tr><td><strong>Bill no.</strong><br><?php echo e($bill->bill_number); ?></td><td><strong>Type</strong><br><?php echo e(ucfirst($bill->bill_type)); ?></td><td><strong>Status</strong><br><?php echo e(str_replace('_', ' ', ucfirst($bill->status))); ?></td></tr>
<tr><td><strong>Project</strong><br><?php echo e($bill->project->project_name); ?></td><td><strong>Bill date</strong><br><?php echo e($bill->bill_date->format('d M Y')); ?></td><td><strong>Period</strong><br><?php echo e($bill->billing_period_start->format('d M Y')); ?> to <?php echo e($bill->billing_period_end->format('d M Y')); ?></td></tr>
</table>
<h2>Certified work</h2>
<table class="items"><thead><tr><th style="width:12%">Item</th><th>Description</th><th class="num" style="width:13%">Qty</th><th class="num" style="width:15%">Rate</th><th class="num" style="width:18%">Amount</th></tr></thead><tbody>
<?php $__currentLoopData = $bill->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><?php echo e($item->boqItem->item_number); ?></td><td><?php echo e($item->boqItem->description); ?></td><td class="num"><?php echo e($item->quantity_billed); ?></td><td class="num"><?php echo e($item->rate); ?></td><td class="num"><?php echo e($item->amount); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table>
<table class="totals">
<tr><td>Previous certified</td><td class="num"><?php echo e($bill->previous_certified_amount); ?></td></tr>
<tr><td>Current work value</td><td class="num"><?php echo e($bill->current_work_value); ?></td></tr>
<tr><td>Deductions</td><td class="num"><?php echo e($bill->deductions); ?></td></tr>
<tr><td>Taxes / TDS</td><td class="num"><?php echo e($bill->taxes); ?></td></tr>
<tr class="total"><td>Net payable</td><td class="num"><?php echo e($bill->net_payable); ?></td></tr>
<tr><td>Paid</td><td class="num"><?php echo e($bill->paidAmount()); ?></td></tr>
<tr class="total"><td>Outstanding</td><td class="num"><?php echo e($bill->outstandingAmount()); ?></td></tr>
</table>
<div class="footer">Confidential financial document. Access and export are audit logged.</div>
</body></html>
<?php /**PATH /Users/himangshusarma/Desktop/MyProjects/kuwalee-siteflow-complete/backend/resources/views/pdf/bill.blade.php ENDPATH**/ ?>