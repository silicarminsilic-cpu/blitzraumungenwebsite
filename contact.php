<?php
// Kontaktformular Blitz Räumungen – sendet Anfragen per E-Mail.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const TO   = 'info@blitztransport.ch';
const FROM = 'noreply@blitz-raeumungen.ch';   // Absender muss zur Domain gehören (bei cyon als Postfach/Alias anlegen)
const MAX_PER_HOUR = 5;

function out($code, $arr) { http_response_code($code); echo json_encode($arr); exit; }
function clean($s, $max) { $s = trim((string)$s); $s = str_replace(["\r","\n"], ' ', $s); return mb_substr($s, 0, $max); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok'=>false]);
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) out(400, ['ok'=>false]);

// Spam-Schutz: Honeypot, zu schnelles Absenden
if (!empty($in['website'])) out(200, ['ok'=>true]);
if ((int)($in['elapsed'] ?? 0) < 2500) out(200, ['ok'=>true]);

// Rate-Limit pro IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'x';
$f  = sys_get_temp_dir() . '/br_rl_' . md5($ip);
$hits = array_filter(is_file($f) ? (array)json_decode(file_get_contents($f), true) : [], fn($t) => $t > time() - 3600);
if (count($hits) >= MAX_PER_HOUR) out(429, ['ok'=>false]);
$hits[] = time(); file_put_contents($f, json_encode(array_values($hits)));

$name  = clean($in['name'] ?? '', 120);
$email = clean($in['email'] ?? '', 160);
$phone = clean($in['phone'] ?? '', 60);
$type  = clean($in['type'] ?? '', 80);
$place = clean($in['place'] ?? '', 120);
$when  = clean($in['when'] ?? '', 120);
$msg   = trim(mb_substr((string)($in['message'] ?? ''), 0, 4000));

if ($name === '' || $msg === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($in['consent'])) out(422, ['ok'=>false]);

$body = "Neue Räumungsanfrage über blitz-raeumungen.ch\n\n"
  . "Name: $name\nE-Mail: $email\nTelefon: $phone\nArt der Anfrage: $type\nOrt/PLZ: $place\nZeitraum: $when\n\nNachricht:\n$msg\n\n"
  . "Gesendet: " . date('d.m.Y H:i') . " (Server-Zeit)\n";

$headers = "From: Blitz Räumungen <" . FROM . ">\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$ok = mail(TO, '=?UTF-8?B?' . base64_encode("Neue Anfrage: $type – $name") . '?=', $body, $headers);
out($ok ? 200 : 500, ['ok'=>$ok]);
