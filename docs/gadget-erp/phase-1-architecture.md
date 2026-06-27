# Gadget ERP — ფაზა 1: არქიტექტურა და გეგმა

> სტატუსი: **ფაზა 1 (გეგმა) — დასტურის მოლოდინში.** კოდი არ იწერება „approved"-მდე.
> Stack: Laravel 11 · Blade + Livewire · Tailwind · Spatie permissions/activitylog · Sanctum · TOTP 2FA · Georgian-first.
> ფული: ₾ (`*_minor`, bigInteger თეთრებში). ფისკალური/იურიდიული დოკუმენტები ქართულად.
> განახლება: ჩართულია review ცვლილებები (procurement დომენი, COGS→S1, TBC cashback off-book, დამატებითი FK/ცხრილები).

---

## 0. საბაზისო არქიტექტურული გადაწყვეტილებები

| # | Decision | Reason | Implementation |
|---|----------|--------|----------------|
| 0.1 | Gadget ERP = Martva-ს იგივე monolith codebase-ის გაგრძელება (ახალი modules namespace) | ერთიანი auth, Spatie roles, activitylog, deploy pipeline | `app/Modules/{Pos,Inventory,Procurement,Warranty,Delivery,B2B,Hr,Reporting,Integrations}` PSR-4; Livewire v3 |
| 0.2 | ყველა გარე ინტეგრაცია გადის `IntegrationGateway` abstraction-ში + `integration_logs` audit | silent-failure-ის დაცვა ერთ ფენაში; retry/idempotency ცენტრალურად | `Integrations/Contracts/*` + `VerifiesDataChange` trait |
| 0.3 | ფული — `bigInteger` თეთრებში (`amount_minor`), არასდროს float | ფინანსური სიზუსტე, COGS/დღგ დამრგვალება | `MoneyCast`, ჩვენება `₾` |
| 0.4 | offline-first POS: ჩეკი ჯერ ლოკალურად idempotent `sale_uuid`-ით, fiscal/waybill — async queue + verification | ინტერნეტის წყვეტა ფილიალში ≠ გაყიდვის შეჩერება | `pos_sales.fiscal_status` state machine |
| 0.5 | multi-location: ერთი DB, `branch_id` scope ყველგან + Spatie team-scoped permissions | 16 ფილიალი, ცენტრალური რეპორტინგი | global scope `BelongsToBranch` |
| 0.6 | COGS = weighted average, წყარო procurement (goods_receipt), არა S8 | რეალური თვითღირებულება პირველივე გაყიდვიდან | `products.cost_minor` განახლდება goods_receipt-ზე; snapshot sale_item-ზე S2-დან |

---

## 1. მოდულების რუკა + დამოკიდებულებების გრაფი

```
                         ┌──────────────────────┐
                         │  CORE (Martva base)  │
                         │ Auth · Spatie roles  │
                         │ activitylog · Sanctum│
                         │ Branch · TOTP 2FA    │
                         └──────────┬───────────┘
                                    │
   ┌────────────┬───────────┬───────┼───────────┬────────────┬───────────────┐
   ▼            ▼           ▼       ▼           ▼            ▼               ▼
┌────────┐ ┌──────────┐ ┌────────┐ ┌──────┐ ┌──────────┐ ┌──────────┐ ┌──────────────┐
│Procure │ │Inventory │ │Pricing │ │  HR  │ │ Customer │ │   ...    │ │ Integrations │
│-ment   │ │ / SKU    │ │        │ │/Shift│ │ /B2B     │ │          │ │  Gateway     │
│sup/PO/ │ │multi-loc │ │price   │ │      │ │          │ │          │ │ RS.ge/FINA/  │
│GRN     │ │stock     │ │lists   │ │      │ │          │ │          │ │ Term/Glovo/  │
└───┬────┘ └────┬─────┘ └───┬────┘ └──┬───┘ └────┬─────┘ │          │ │ Wolt         │
    │  COGS     │           │         │          │       │          │ └──────┬───────┘
    └───────────┴───────────┴─────────┴──────────┘                          │
                            ▼                                                │
                     ┌──────────────┐                                       │
                     │     POS      │◀───────────────────────────────────────┤
                     │ single-window│  (fiscal, terminal, waybill verify)     │
                     └──────┬───────┘                                       │
        ┌──────────┬────────┼─────────────┬─────────────┐                  │
        ▼          ▼        ▼             ▼             ▼                   │
   ┌────────┐ ┌────────┐ ┌─────────────┐ ┌──────────────┐                  │
   │Warranty│ │Returns │ │ Glovo/Wolt  │ │  Accounting  │◀─────────────────┘
   │ /RMA   │ │(legal  │ │  Inbox→POS  │ │  bridge      │
   │↔supplier││ terms) │ │             │ │ FINA/COGS/   │
   └────────┘ └────────┘ └─────────────┘ │ TBC off-book │
                              │           └──────────────┘
                              ▼
                       ┌─────────────┐
                       │  Reporting  │ (reads from all)
                       └─────────────┘
```

| მოდული | დგას ამ მოდულებზე |
|--------|-------------------|
| Core | — (Martva არსებული) |
| Procurement (suppliers/PO/GRN) | Core, Inventory |
| Inventory/SKU | Core |
| Pricing | Core, Inventory |
| Integrations Gateway | Core |
| Customer/B2B | Core, Pricing |
| HR/Shift | Core |
| POS | Inventory, Pricing, Integrations, HR, Customer, Procurement (COGS) |
| Terminal+Fiscal | Integrations, POS |
| Warranty/RMA | POS, Inventory, Customer, Procurement (supplier) |
| Returns | POS, Integrations (fiscal credit) |
| Glovo/Wolt | Inventory, POS, Integrations |
| Accounting bridge (FINA/COGS/TBC) | POS, Inventory, Procurement, Integrations |
| Reporting | ყველა ზემოთა |

---

## 2. ERD / მონაცემთა მოდელი

### Core / Org

| Table | ძირითადი ველები | FK / კავშირი |
|-------|------------------|--------------|
| `branches` | id, name, code, city, brand_id, address, rs_branch_code, is_active | brand_id→brands |
| `brands` | id, name, is_flagship | — |
| `users` | id, name, email, branch_id, totp_secret, is_active | branch_id→branches |
| `roles`,`permissions`,`model_has_roles` | Spatie (team_id = branch_id) | — |
| `activity_log` | Spatie activitylog | — |

### Procurement (ახალი დომენი)

| Table | ძირითადი ველები | FK |
|-------|------------------|----|
| `suppliers` | id, name, tax_id, phone, email, payment_terms_days, is_active | — |
| `purchase_orders` | id, supplier_id, branch_id, status(draft/sent/partial/received/closed), total_minor, expected_at, created_by | supplier_id→suppliers, branch_id |
| `purchase_order_lines` | id, purchase_order_id, product_variant_id, qty_ordered, qty_received, unit_cost_minor | po_id→purchase_orders, variant_id |
| `goods_receipts` | id, purchase_order_id, supplier_id, branch_id, waybill_id, status, received_by, received_at | po_id, supplier_id, branch_id, waybill_id→rs_waybills |
| `goods_receipt_lines` | id, goods_receipt_id, product_variant_id, qty, unit_cost_minor, serial_nos(JSON) | grn_id→goods_receipts, variant_id |

> **COGS:** `goods_receipt_lines` confirm-ზე ხდება `products.cost_minor` weighted-average გადათვლა.

### Inventory / Product

| Table | ძირითადი ველები | FK |
|-------|------------------|----|
| `products` | id, sku, name_ka, name_en, brand_id, category_id, vat_applicable, barcode, unit, is_serialized, **cost_minor (weighted avg)** | brand_id, category_id |
| `product_categories` | id, name_ka, parent_id, vat_default | parent_id→self |
| `product_variants` | id, product_id, model_compat(JSON: cases/glass→models), variant_sku, barcode | product_id→products |
| `stock_levels` | id, product_variant_id, branch_id, qty, min_qty, max_qty, reserved_qty | variant_id, branch_id |
| `serial_items` | id, product_variant_id, branch_id, serial_no, imei, status(in_stock/**in_transit**/sold/rma), sale_item_id | variant_id, branch_id, sale_item_id |
| `stock_movements` | id, type(in/out/transfer/adjust/inventory), variant_id, from_branch_id, to_branch_id, qty, waybill_id, cost_minor, ref_type, ref_id | waybill_id→rs_waybills |
| `inventory_counts` | id, branch_id, status, started_by, closed_at | branch_id |
| `inventory_count_lines` | id, count_id, variant_id, system_qty, counted_qty, diff | count_id |

### Pricing

| Table | ძირითადი ველები | FK |
|-------|------------------|----|
| `price_lists` | id, name, brand_id, branch_id(nullable), type(retail/wholesale), currency='GEL' | brand_id, branch_id |
| `price_list_items` | id, price_list_id, product_variant_id, price_minor, vat_included | price_list_id, variant_id |
| `b2b_price_tiers` | id, customer_id, price_list_id, discount_pct | customer_id, price_list_id |

### Customer / B2B

| Table | ძირითადი ველები | FK |
|-------|------------------|----|
| `customers` | id, type(retail/b2b), name, tax_id, phone, email, is_vat_payer | — |
| `b2b_accounts` | id, customer_id, credit_limit_minor, balance_minor, payment_terms_days | customer_id |
| `b2b_orders` | id, customer_id, branch_id, status, total_minor, waybill_id, invoice_id | customer_id, branch_id |
| `b2b_order_lines` | id, order_id, variant_id, qty, price_minor | order_id |

### POS / Sales

| Table | ძირითადი ველები | FK |
|-------|------------------|----|
| `pos_shifts` | id, branch_id, user_id, opened_at, closed_at, opening_cash_minor, closing_cash_minor, x_report_json, z_report_json, status | branch_id, user_id |
| `pos_sales` | id, sale_uuid(unique), shift_id, branch_id, cashier_id, customer_id, channel(retail/glovo/wolt/b2b), subtotal_minor, discount_minor, vat_minor, total_minor, fiscal_status(pending/sent/verified/failed), fiscal_receipt_no, waybill_id, status | shift_id, branch_id, customer_id |
| `pos_sale_items` | id, sale_id, variant_id, serial_item_id, qty, unit_price_minor, discount_minor, vat_minor, **cost_minor (COGS snapshot S2-დან)** | sale_id, variant_id |
| `pos_payments` | id, sale_id, method(cash/card/tbc_cashback/mixed), amount_minor, terminal_txn_id, status | sale_id |
| `pos_returns` | id, original_sale_id, branch_id, cashier_id, reason, refund_minor, fiscal_credit_no, legal_window_ok, status | original_sale_id |
| `cash_movements` | id, shift_id, branch_id, type(in/out/payout/deposit), amount_minor, reason, user_id | shift_id, branch_id |

> **Discount override:** `pos_sale_items` / `pos_sales`-ს დაემატება `discount_override_by` (nullable FK→users) — ლიმიტს ზევით ფასდაკლება მოითხოვს `pos.discount.override` permission-ს და approver-ს.

### Warranty / RMA

| Table | ძირითადი ველები | FK |
|-------|------------------|----|
| `warranties` | id, sale_item_id, serial_item_id, customer_id, start_date, end_date, terms_months | sale_item_id, serial_item_id |
| `rma_requests` | id, warranty_id, customer_id, branch_id, **supplier_id (nullable)**, status(received/diagnosis/repair/returned/sent_to_supplier), legal_deadline, supplier_ref | warranty_id, branch_id, **supplier_id→suppliers** |
| `rma_events` | id, rma_id, status_from, status_to, note, user_id, created_at | rma_id |

### Delivery (Glovo/Wolt)

| Table | ძირითადი ველები | FK |
|-------|------------------|----|
| `delivery_orders` | id, platform(glovo/wolt), external_id(unique), branch_id, status, items_json, total_minor, commission_minor, pos_sale_id, accepted_at | branch_id, pos_sale_id |
| `delivery_menu_sync` | id, platform, variant_id, external_item_id, last_synced_at, sync_status | variant_id |
| `delivery_reconciliations` | id, platform, branch_id, date, platform_total_minor, erp_total_minor, diff_minor, status | branch_id |

### Integrations (audit / state)

| Table | ძირითადი ველები | FK |
|-------|------------------|----|
| `integration_logs` | id, provider, operation, request_json, response_json, http_status, success, verified, idempotency_key, ref_type, ref_id, created_at | polymorphic ref |
| `rs_waybills` | id, **direction(in/out)**, type(transfer/wholesale/return/**purchase**), status, rs_waybill_no, seller_tax_id, buyer_tax_id, ref_type, ref_id, activated_at, closed_at | polymorphic |
| `rs_invoices` | id, direction(out/in), rs_invoice_no, status(draft/sent/confirmed), customer_id, vat_minor, total_minor, **ref_type, ref_id (polymorphic: sale/b2b_order/goods_receipt)** | customer_id, polymorphic |
| `fina_sync_logs` | id, entity(price/document/cogs), direction, payload_json, success, verified, created_at | — |

---

## 3. როლები / უფლებების matrix (Spatie, team-scoped = branch)

permission ფორმატი `{module}.{action}`; role-ები branch-scoped (გარდა `super_admin`/`accountant`/`hq_manager`).

| Role | POS | Inventory | Procurement | Pricing | Warranty | Delivery | B2B | Reporting | Integrations | Branch scope |
|------|-----|-----------|-------------|---------|----------|----------|-----|-----------|--------------|--------------|
| super_admin | all | all | all | all | all | all | all | all | all | ყველა |
| hq_manager | view | view | view | view | view | view | view | all | view logs | ყველა |
| accountant | view | view | view | edit | view | reconcile | view | financial | retry/verify | ყველა |
| branch_manager | all | manage,transfer | receive | view | manage | manage | view | branch | view logs | საკუთარი |
| cashier | sale,return,shift,**discount.override(ლიმიტამდე)** | view | — | view | create RMA | accept/handoff | — | own shift | — | საკუთარი |
| warehouse | — | manage,transfer,count | po,receive | — | — | — | — | stock | waybill open | საკუთარი |
| b2b_manager | — | view | — | view | — | — | all | b2b | invoice | ყველა |

permission ნიმუშები:
```
pos.sale.create  pos.return.create  pos.discount.apply  pos.discount.override  pos.shift.open  pos.shift.close
pos.cash.movement
inventory.view  inventory.transfer.create  inventory.transfer.approve  inventory.count.manage
procurement.po.create  procurement.po.approve  procurement.grn.receive
pricing.view  pricing.edit
warranty.rma.create  warranty.rma.update_status  warranty.rma.send_supplier
delivery.order.accept  delivery.order.reject  delivery.reconcile
b2b.order.create  b2b.credit.override
reporting.branch.view  reporting.financial.view
integration.retry  integration.verify  waybill.open  invoice.issue
```

---

## 4. ინტეგრაციების კონტრაქტები

> ⚠ = legal/spec checkpoint — RS.ge/მომწოდებლის რეალური სპეცი დასადასტურებელია (§9). endpoint ფაქტად არ ცხადდება დადასტურების გარეშე.

### 4.1 RS.ge Fiscalization
| ასპექტი | კონტრაქტი |
|---------|-----------|
| Endpoint | ⚠ RS.ge fiscal API / software fiscalization module (SDK §9) |
| Auth | service user + token / certificate |
| Operation | `createReceipt(sale)` → fiscal_no |
| Webhook | არ არსებობს — poll status |
| Idempotency | `sale_uuid` → `idempotency_key` |
| Retry | exponential 2/4/8/16s, max 4; failure→`fiscal_status=failed`, queue retry |
| **Verification** | `getReceipt(fiscal_no)` → total/vat match → `fiscal_status=verified` |

### 4.2 RS.ge Waybill (ზედნადები) — outbound + inbound
| ასპექტი | კონტრაქტი |
|---------|-----------|
| Endpoint | ⚠ RS.ge waybill SOAP/REST (მოქმედი ვერსია §9) |
| Operations (out) | `save_waybill` → `send_waybill` (activate) → `close_waybill` |
| Operations (in) | inbound `purchase` waybill: `get_waybill` → **confirm** მიღებაზე (goods_receipt) |
| Trigger | transfer/wholesale/Glovo/Wolt (out); შესყიდვა მომწოდებლისგან (in) |
| Idempotency | `ref_type+ref_id` |
| **Verification** | out: `status==ACTIVE` გადაადგილებამდე; in: `status==CONFIRMED` GRN-მდე; mismatch → block |

### 4.3 RS.ge ანგარიშ-ფაქტურა (invoice)
| ასპექტი | კონტრაქტი |
|---------|-----------|
| Endpoint | ⚠ RS.ge invoice API §9 |
| Operations | `save_invoice` → `send_invoice` → counterpart `confirm` |
| Trigger | B2B/wholesale დღგ-იანი (out); შესყიდვა (in) |
| **Verification** | `get_invoice.status` poll; CONFIRMED-მდე ღია |

### 4.4 FINA
| ასპექტი | კონტრაქტი |
|---------|-----------|
| Method | ⚠ API / DB / file §9 |
| Sync | price pull (FINA→ERP), document/COGS push (ERP→FINA), TBC off-book settlement |
| Idempotency | document hash |
| **Verification** | push-ის შემდეგ read-back + თანხების შედარება; mismatch→`verified=false` |

### 4.5 ბარათის ტერმინალი (TBC/BoG)
| ასპექტი | კონტრაქტი |
|---------|-----------|
| Protocol | ⚠ ECR/SoftPOS, მოდელი §9 |
| Operation | `pay(amount)`→txn_id; `void`/`refund` |
| Offline | ტერმინალის status poll; offline→manual flag |
| **Verification** | bank txn status==APPROVED + amount match → `pos_payments.status=verified` |

### 4.6 Glovo API
| ასპექტი | კონტრაქტი |
|---------|-----------|
| Auth | ⚠ partner OAuth §9 |
| Webhook | new order → inbox |
| Operations | accept/reject, menu sync, status update |
| Idempotency | `external_id` unique |
| **Verification** | accept-ის შემდეგ status read-back; EOD reconciliation |

### 4.7 Wolt API
| ასპექტი | კონტრაქტი |
|---------|-----------|
| Auth | ⚠ Wolt Merchant API OAuth §9 |
| Webhook | order.created |
| Operations | accept/reject, menu/inventory sync, ready/handoff |
| **Verification** | same as Glovo |

---

## 5. იურიდიული შესაბამისობის რუკა

| ოპერაცია | RS.ge დოკუმენტი | დღგ | Legal checkpoint |
|----------|------------------|-----|------------------|
| საცალო გაყიდვა | ფისკალური ჩეკი | 18% | ⚠ ჩეკის რეკვიზიტები (ს/კ, ფისკ.№, თარიღი, დღგ) |
| **შესყიდვა მომწოდებლისგან** | **inbound waybill (purchase) + ანგ-ფაქტურა** | 18% in | ⚠ inbound waybill confirm + invoice confirm GRN-მდე |
| ფილიალთაშორისი გადატანა | ზედნადები (transfer, out) | — | ⚠ transfer waybill type code; serial → `in_transit` |
| დაბრუნება | fiscal credit / შესწორების ჩეკი | reverse vat | ⚠ კანონისმიერი ვადა (`legal_window_ok`) + credit receipt |
| საბითუმო მიწოდება | ზედნადები (out) + ანგ-ფაქტურა | 18% | ⚠ invoice confirm flow |
| Glovo/Wolt გაყიდვა | ფისკალური ჩეკი (+ ზედნადები საჭიროებისას) | 18% | ⚠ courier handoff = movement? |
| **TBC cashback** | — (ბანკის დაფინანსებული) | — | ⚠ **off-book settlement** — არა expense/discount; მხოლოდ აღრიცხვა/გამიჯვნა accounting bridge-ში |
| დღგ-ის ზღვარი | — | registration logic | ⚠ კომპანიის დღგ status §9 |
| პერსონალური მონაცემები | — | — | data minimization + consent |

> ⚠ ყველა „⚠" ნაბიჯი ფაზა 2-ის კოდის წერამდე დასტურდება ბუღალტერთან + RS.ge მოქმედ API დოკუმენტაციასთან.

---

## 6. Single-window UX flow (მოლარის ერთი ეკრანი)

ერთი Livewire `PosTerminal` component, zone-ები keyboard-driven, modal-ები ფანჯრის გადართვის გარეშე.

```
┌─────────────────────────────────────────────────────────────────────┐
│ TOP BAR: ფილიალი | მოლარე | ცვლა #123 (OPEN) | 🟢 fiscal 🟢 terminal │
├──────────────────────────────────────┬──────────────────────────────┤
│ ZONE A — CART (კალათა)                │ ZONE B — ACTIONS / PAYMENT   │
│ ┌──────────────────────────────────┐ │  [F2] ფასდაკლება             │
│ │ SKU | დასახ. | რაოდ | ფასი | სულ  │ │  [F3] დაბრუნება              │
│ │ scan/QR → ავტომატური დამატება    │ │  [F4] კლიენტი/B2B             │
│ └──────────────────────────────────┘ │  [F8] Glovo/Wolt inbox       │
│ [scan input — focus default]          │  გადახდა [F9]:               │
│ ZONE C — ITEM DETAIL                  │   ნაღდი | ბარათი | cashback  │
│  serial/IMEI მიბმა (serialized SKU)   │   შერეული (mixed)            │
│  გარანტიის ვადა auto                  │  ჯამი: ₾0.00  დღგ: ₾0.00     │
├──────────────────────────────────────┴──────────────────────────────┤
│ BOTTOM: [F1] ცვლა (X/Z) | [F7] cash in/out | [Esc] cancel | [Enter] OK│
└──────────────────────────────────────────────────────────────────────┘
```

| მოვლენა | რა ხდება (ფანჯრის გადართვის გარეშე) |
|---------|--------------------------------------|
| scan | barcode→variant→cart; serialized→inline serial prompt |
| payment [F9] | inline modal: მეთოდი → terminal call (async) → fiscal call (async) → verification badge; წითელზე ჩეკი არ იბეჭდება, retry |
| return [F3] | original sale lookup → `legal_window_ok` → refund + fiscal credit |
| discount [F2] | ლიმიტამდე — cashier; ზევით — `discount.override` + approver inline |
| cash [F7] | `cash_movements` in/out ცვლის ფარგლებში |
| shift-close [F1] | X (read-only) / Z (close): cash reconciliation + fiscal totals verify |
| Glovo/Wolt [F8] | inbox slide-over: accept→order→„POS-ში ჩასმა" ერთ ჩეკად |

Shortcuts: F1 shift, F2 discount, F3 return, F4 customer, F7 cash, F8 delivery, F9 pay, Enter confirm, Esc cancel, `+/-` qty.

---

## 7. სპრინტების გეგმა (MVP → სრული)

| სპრინტი | მოდულები | Deliverable | დამოკიდებულება |
|---------|----------|-------------|----------------|
| S0 | Core scaffold | namespace, Branch, Brand, Spatie teams, IntegrationGateway | Martva base |
| **S1 (MVP core)** | **Procurement + Inventory/SKU + Pricing** | suppliers, purchase_orders, goods_receipts(+lines), products(+**cost_minor weighted avg**), variants, stock_levels, price_lists, transfer; COGS source ჩართულია | S0 |
| S2 (MVP POS) | POS sale + shift | cart, sale, payment(cash), **sale_item.cost_minor snapshot**, cash_movements, X/Z report, barcode | S1 |
| S3 (legal core) | Fiscal + Terminal | RS.ge fiscalization + verification, card terminal, receipt print | S2, §9 |
| S4 | Waybill (in/out) + Returns | transfer waybill, **inbound purchase waybill confirm (GRN)**, legal return window, fiscal credit | S3 |
| S5 | Warranty/RMA | serial/IMEI, RMA lifecycle (↔supplier), legal deadlines | S2, S4 |
| S6 | Customer/B2B | b2b orders, credit limit, invoice (RS.ge) | S1, S3 |
| S7 | Glovo/Wolt | inbox, menu sync, accept→POS, reconciliation | S2, S3 |
| S8 | FINA bridge + TBC settlement | price sync, document push + verification, **TBC off-book settlement** | S2–S6 |
| S9 | Reporting/Dashboard | sales/branch/SKU, margin, dead stock, AI enrichment | ყველა |

> ცვლილება: COGS გადატანილია S8→S1 (weighted average goods_receipt-ზე); sale_item snapshot მუშაობს S2-დან.

---

## 8. რისკები + silent-failure წერტილები

| წერტილი | სად „success" ფარავს წარუმატებლობას | გადამოწმება |
|---------|--------------------------------------|-------------|
| Fiscal | HTTP 200, ჩეკი RS.ge-ზე არ აისახა | `getReceipt` read-back, total/vat match |
| Waybill (out) | save OK, activate ჩავარდა → უკანონო გადაადგილება | status==ACTIVE-მდე movement block |
| Waybill (in) | inbound confirm გამოტოვებული → GRN/COGS არასწორი | status==CONFIRMED-მდე GRN block |
| Terminal | „APPROVED" UI-ზე, txn void/timeout | bank txn status + amount match |
| FINA push | file/API „OK", თანხა არ შევიდა | read-back + amount compare |
| TBC cashback | discount/expense-ად ჩაიწერა → მოგების დამახინჯება | off-book settlement, expense-ში არ აისახება |
| COGS | weighted avg არ გადაითვალა GRN-ზე / snapshot ცვლილების შემდეგ | `products.cost_minor` recompute on GRN; `sale_item.cost_minor` snapshot |
| Glovo/Wolt accept | accept OK, order expired | status re-poll, EOD reconciliation diff |
| Stock | concurrent double-sell serialized | DB lock + `serial_items.status` guard (incl. `in_transit`) |
| Offline POS | queue drop → fiscal-მდე ვერ მივიდა | `fiscal_status=pending` alert, retry queue |

---

## 9. ღია კითხვები (პასუხი საჭიროა ფაზა 2-მდე)

| # | კითხვა |
|---|--------|
| 1 | Glovo/Wolt: partner/merchant API თუ მხოლოდ UI? OAuth credentials + webhook URL? |
| 2 | ფისკალი: სერტიფიცირებული აპარატი თუ RS.ge software module? მომწოდებელი/SDK? |
| 3 | ტერმინალი: TBC თუ BoG? მოდელი + protocol (ECR/SoftPOS)? |
| 4 | RS.ge: მოქმედი API ვერსია, service user/credentials, sandbox? |
| 5 | FINA: integration მეთოდი (API/DB/file)? price type ID-ები? COGS წყაროს დადასტურება? |
| 6 | დღგ: კომპანია დღგ-ის გადამხდელია? რომელი SKU კატეგორიები დღგ-ის გარეშე? |
| 7 | Price lists: ბრენდების ჭრილში ფილიალის price list ზუსტი სტრუქტურა (16×ბრენდი)? |
| 8 | Martva codebase: ERP modules `hangover/backend`-ში ვაშენო? (ნავარაუდევია — დასტური) |
| 9 | Glovo/Wolt courier handoff — waybill სავალდებულოა თუ მხოლოდ ფისკალი? |
| 10 | **TBC cashback off-book settlement**: ბუღალტრული რეგისტრაცია ზუსტად როგორ ხდება FINA-ში (settlement account)? |

---

## სტატუსი

**ფაზა 1 დასრულდა (review ცვლილებებით). დასტურის მოლოდინში.**
კოდი არ იწყება „approved"-მდე. დასტურის შემდეგ — S0 → S1, თითო მოდულზე:
migration → Model + Livewire + Blade → ტესტი → commit message → „შემდეგი მოდული?".
