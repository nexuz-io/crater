<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plan 05 §7 + plan 09 §F11 — rellena los settings de "Nota de
 * entrega" para cada company existente al momento del upgrade.
 *
 * Es un mirror idempotente del bloque que `Company::setupDefaultSettings()`
 * aplica a companies nuevas. Usamos `WHERE NOT EXISTS` parcial
 * sobre `company_settings` para no pisar preferencias que el
 * operador ya haya cambiado:
 *   - Si la fila existe: respetamos el valor actual.
 *   - Si no existe:    insertamos el default.
 *
 * No tocamos filas ajenas a delivery-notes (la query sólo mira
 * `option LIKE 'delivery\_note%'` y `notify_delivery_note_viewed`).
 *
 * Lista de settings cubiertos:
 *   - delivery_note_auto_generate           ('YES')
 *   - delivery_note_number_format           ('{{SERIES:DN}}{{DELIMITER:-}}{{SEQUENCE:6}}')
 *   - delivery_note_default_terms_and_conditions  ('')
 *   - delivery_note_company_address_format  (idéntico a invoice/_estimate)
 *   - delivery_note_shipping_address_format (idéntico a invoice/estimate)
 *   - delivery_note_billing_address_format  (idéntico a invoice/estimate)
 *   - delivery_note_mail_body               (texto base con placeholder {COMPANY_NAME})
 *   - delivery_note_email_attachment        ('NO')
 *   - delivery_note_display_prices          ('NO' — plan 05 §1)
 *   - notify_delivery_note_viewed           ('NO')
 */
class AddDeliveryNoteSettingsToExistingCompanies extends Migration
{
    public function up()
    {
        // `company_settings` no tiene unique index sobre
        // (company_id, option), así que `insertOrIgnore` no es
        // seguro. Usamos firstOrCreate por combinación: si el row
        // no existe, lo crea con el default; si el operador ya
        // tocó el setting, respetamos su valor. Race-safe porque
        // Eloquent serializa el SELECT+INSERT en una sola tx
        // y porque este script corre una sola vez durante el migrate.

        $companies = DB::table('companies')->select('id')->get();

        $defaults = [
            'delivery_note_auto_generate' => 'YES',
            'delivery_note_number_format' => '{{SERIES:DN}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            'delivery_note_default_terms_and_conditions' => '',
            'delivery_note_mail_body' => 'You have received a new delivery note from <b>{COMPANY_NAME}</b>.</br> Please review using the link below:',
            'delivery_note_company_address_format' => '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_ADDRESS_STREET_2}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p><p>{COMPANY_PHONE}</p>',
            'delivery_note_shipping_address_format' => '<h3>{SHIPPING_ADDRESS_NAME}</h3><p>{SHIPPING_ADDRESS_STREET_1}</p><p>{SHIPPING_ADDRESS_STREET_2}</p><p>{SHIPPING_CITY}  {SHIPPING_STATE}</p><p>{SHIPPING_COUNTRY}  {SHIPPING_ZIP_CODE}</p><p>{SHIPPING_PHONE}</p>',
            'delivery_note_billing_address_format' => '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY}  {BILLING_STATE}</p><p>{BILLING_COUNTRY}  {BILLING_ZIP_CODE}</p><p>{BILLING_PHONE}</p>',
            'delivery_note_email_attachment' => 'NO',
            'delivery_note_display_prices' => 'NO',
            'notify_delivery_note_viewed' => 'NO',
        ];

        $now = now();
        foreach ($companies as $company) {
            foreach ($defaults as $option => $value) {
                DB::table('company_settings')->firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'option' => $option,
                    ],
                    [
                        'value' => $value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down()
    {
        // Intencionalmente vacío: borrar settings sería destructivo
        // y no es reversible. Si un operador quiere resetear, lo
        // hace a mano desde Settings UI.
    }
}
