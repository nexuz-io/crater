<?php

use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Plan 05 §7 + plan 09 §F11 — rellena los settings de "Nota de
 * entrega" para cada company existente al momento del upgrade.
 *
 * Es un mirror idempotente del bloque que `Company::setupDefaultSettings()`
 * aplica a companies nuevas. Idempotencia:
 *   - Si la fila (company_id, option) NO existe: la creamos con
 *     el default de plan 05/09 §F11.
 *   - Si la fila YA existe (operador cambió el setting): la
 *     respetamos. NO pisamos valores personalizados.
 *
 * Implementación: usamos `CompanySetting::firstOrCreate()` del
 * modelo Eloquent (no del query builder — DB::table()->firstOrCreate
 * no existe). La query del modelo sí soporta este método de forma
 * nativa, sin requerir índice unique (la unicidad la garantiza la
 * propia combinación company_id+option por convención de la app).
 *
 * Lista de settings cubiertos:
 *   - delivery_note_auto_generate           ('YES')
 *   - delivery_note_number_format           ('{{SERIES:DN}}{{DELIMITER:-}}{{SEQUENCE:6}}')
 *   - delivery_note_default_terms_and_conditions  ('')
 *   - delivery_note_company_address_format  (idéntico a invoice/estimate)
 *   - delivery_note_shipping_address_format (idéntico a invoice/estimate)
 *   - delivery_note_billing_address_format  (idéntico a invoice/estimate)
 *   - delivery_note_mail_body               (texto base con {COMPANY_NAME})
 *   - delivery_note_email_attachment        ('NO')
 *   - delivery_note_display_prices          ('NO' — plan 05 §1)
 *   - notify_delivery_note_viewed           ('NO')
 */
class AddDeliveryNoteSettingsToExistingCompanies extends Migration
{
    public function up()
    {
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

        Company::all()->each(function ($company) use ($defaults) {
            foreach ($defaults as $option => $value) {
                // Model Eloquent — firstOrCreate SÍ existe sólo en
                // el model layer (no en DB::table()). Si la fila
                // existe, NO toca su valor (preserva overrides
                // manuales del operador).
                CompanySetting::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'option' => $option,
                    ],
                    ['value' => $value]
                );
            }
        });
    }

    public function down()
    {
        // Intencionalmente vacío: borrar settings sería destructivo
        // y no es reversible. Si un operador quiere resetear, lo
        // hace a mano desde Settings UI.
    }
}
