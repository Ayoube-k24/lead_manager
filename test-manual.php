<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;

echo "=== Test de Distribution Automatique ===\n\n";

// Test 1: Mode round_robin
echo "Test 1: Mode round_robin\n";
$callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
$role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent', 'slug' => 'agent']);
$agent = User::factory()->create(['role_id' => $role->id, 'call_center_id' => $callCenter->id]);
$smtp = SmtpProfile::factory()->create();
$template = EmailTemplate::factory()->create();
$form = Form::factory()->create(['call_center_id' => $callCenter->id, 'smtp_profile_id' => $smtp->id, 'email_template_id' => $template->id]);
$lead = Lead::factory()->create(['form_id' => $form->id, 'call_center_id' => $callCenter->id, 'status' => 'pending_email', 'assigned_to' => null]);

echo "Avant: assigned_to=".($lead->assigned_to ?? 'null')."\n";
$lead->confirmEmail();
$lead->refresh();
echo "Après: assigned_to=".($lead->assigned_to ?? 'null').", status={$lead->status}\n";
echo ($lead->assigned_to ? "✅ SUCCESS\n" : "❌ FAILED\n")."\n";

// Test 2: Mode manual
echo "Test 2: Mode manual\n";
$callCenter2 = CallCenter::factory()->create(['distribution_method' => 'manual']);
$agent2 = User::factory()->create(['role_id' => $role->id, 'call_center_id' => $callCenter2->id]);
$form2 = Form::factory()->create(['call_center_id' => $callCenter2->id, 'smtp_profile_id' => $smtp->id, 'email_template_id' => $template->id]);
$lead2 = Lead::factory()->create(['form_id' => $form2->id, 'call_center_id' => $callCenter2->id, 'status' => 'pending_email', 'assigned_to' => null]);

echo "Avant: assigned_to=".($lead2->assigned_to ?? 'null')."\n";
$lead2->confirmEmail();
$lead2->refresh();
echo "Après: assigned_to=".($lead2->assigned_to ?? 'null').", status={$lead2->status}\n";
echo ($lead2->assigned_to === null ? "✅ SUCCESS (non assigné en mode manual)\n" : "❌ FAILED\n")."\n";

echo "=== Tests terminés ===\n";

