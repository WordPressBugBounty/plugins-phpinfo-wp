<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Mail_Check {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function audit(): array {
        if (!self::_pro()) return [];

        $from   = get_option('admin_email', '');
        $parts  = explode('@', $from);
        $domain = isset($parts[1]) ? strtolower($parts[1]) : '';

        $spf   = $domain ? self::_lookup_spf($domain) : ['present' => false];
        $dmarc = $domain ? self::_lookup_dmarc($domain) : ['present' => false];
        $mx    = $domain ? self::_lookup_mx($domain) : [];

        $smtp_plugin = self::_detect_smtp_plugin();

        return [
            'from_email'   => $from,
            'domain'       => $domain,
            'spf'          => $spf,
            'dmarc'        => $dmarc,
            'mx'           => $mx,
            'smtp_plugin'  => $smtp_plugin,
            'using_php_mail' => empty($smtp_plugin),
        ];
    }

    private static function _lookup_spf(string $domain): array {
        if (!function_exists('dns_get_record')) return ['present' => false, 'error' => 'dns_get_record unavailable'];
        $records = @dns_get_record($domain, DNS_TXT);
        if (!$records) return ['present' => false];
        foreach ($records as $r) {
            $txt = $r['txt'] ?? '';
            if (stripos($txt, 'v=spf1') === 0) {
                $strict = strpos($txt, '-all') !== false;
                $soft   = strpos($txt, '~all') !== false;
                return [
                    'present'  => true,
                    'value'    => $txt,
                    'strict'   => $strict,
                    'soft'     => $soft,
                    'warning'  => (!$strict && !$soft) ? 'SPF has no "all" mechanism — policy is undefined.' : null,
                ];
            }
        }
        return ['present' => false];
    }

    private static function _lookup_dmarc(string $domain): array {
        if (!function_exists('dns_get_record')) return ['present' => false, 'error' => 'dns_get_record unavailable'];
        $records = @dns_get_record('_dmarc.' . $domain, DNS_TXT);
        if (!$records) return ['present' => false];
        foreach ($records as $r) {
            $txt = $r['txt'] ?? '';
            if (stripos($txt, 'v=DMARC1') === 0) {
                preg_match('/p\s*=\s*(\w+)/', $txt, $m);
                $policy = strtolower($m[1] ?? 'none');
                return [
                    'present' => true,
                    'value'   => $txt,
                    'policy'  => $policy,
                    'warning' => $policy === 'none' ? 'DMARC policy is "none" — no enforcement, monitoring only.' : null,
                ];
            }
        }
        return ['present' => false];
    }

    private static function _lookup_mx(string $domain): array {
        if (!function_exists('dns_get_record')) return [];
        $records = @dns_get_record($domain, DNS_MX);
        if (!$records) return [];
        usort($records, function ($a, $b) {
            return ($a['pri'] ?? 99) <=> ($b['pri'] ?? 99);
        });
        $out = [];
        foreach ($records as $r) {
            $out[] = ['priority' => (int)($r['pri'] ?? 0), 'host' => $r['target'] ?? ''];
        }
        return $out;
    }

    private static function _detect_smtp_plugin(): ?string {
        $known = [
            'WP_Mail_SMTP' => 'WP Mail SMTP',
            'WPMailSMTP\\Core' => 'WP Mail SMTP',
            'Easy_WP_SMTP' => 'Easy WP SMTP',
            'FluentMail\\App\\Application' => 'FluentSMTP',
            'POST_SMTP_VERSION' => 'Post SMTP',
            'SendGrid\\WPPlugin' => 'SendGrid',
            'PostmanSMTPVersion' => 'Postman SMTP',
        ];
        foreach ($known as $sym => $name) {
            if (class_exists($sym) || defined($sym)) return $name;
        }
        return null;
    }

    public static function discover_routing_emails(): array {
        if (!self::_pro()) return [];

        $emails = [];
        $admin_email = get_option('admin_email');

        // 1. WordPress Admin Email
        if (is_email($admin_email)) {
            $emails[] = [
                'source' => 'WordPress Admin Email',
                'email'  => $admin_email,
            ];
        }

        // 2. Contact Form 7
        if (post_type_exists('wpcf7_contact_form')) {
            $cf7_forms = get_posts(['post_type' => 'wpcf7_contact_form', 'numberposts' => -1]);
            foreach ($cf7_forms as $form) {
                $mail = get_post_meta($form->ID, '_mail', true);
                if (is_array($mail) && !empty($mail['recipient'])) {
                    $recs = explode(',', $mail['recipient']);
                    foreach ($recs as $rec) {
                        $rec = trim($rec);
                        // Try to extract if it's "Name <email@example.com>"
                        if (preg_match('/<([^>]+)>/', $rec, $m)) {
                            $rec = $m[1];
                        }
                        if (is_email($rec)) {
                            $emails[] = [
                                'source' => 'Contact Form 7: ' . $form->post_title,
                                'email'  => $rec,
                            ];
                        }
                    }
                }
            }
        }

        // 3. WPForms
        if (post_type_exists('wpforms')) {
            $wpf_forms = get_posts(['post_type' => 'wpforms', 'numberposts' => -1]);
            foreach ($wpf_forms as $form) {
                $data = json_decode($form->post_content, true);
                if (isset($data['settings']['notifications'])) {
                    foreach ($data['settings']['notifications'] as $notif) {
                        if (!empty($notif['email'])) {
                            $recs = explode(',', $notif['email']);
                            foreach ($recs as $rec) {
                                $rec = trim($rec);
                                if ($rec === '{admin_email}') {
                                    $rec = $admin_email;
                                }
                                if (is_email($rec)) {
                                    $emails[] = [
                                        'source' => 'WPForms: ' . $form->post_title,
                                        'email'  => $rec,
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        // 4. Gravity Forms
        if (class_exists('GFAPI')) {
            $gf_forms = \GFAPI::get_forms();
            if (is_array($gf_forms)) {
                foreach ($gf_forms as $form) {
                    if (!empty($form['notifications'])) {
                        foreach ($form['notifications'] as $notif) {
                            if (!empty($notif['to'])) {
                                $recs = explode(',', $notif['to']);
                                foreach ($recs as $rec) {
                                    $rec = trim($rec);
                                    if ($rec === '{admin_email}') {
                                        $rec = $admin_email;
                                    }
                                    if (is_email($rec)) {
                                        $emails[] = [
                                            'source' => 'Gravity Forms: ' . ($form['title'] ?? 'Form'),
                                            'email'  => $rec,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // De-duplicate and check MX
        $unique = [];
        foreach ($emails as $e) {
            $key = strtolower($e['email']) . '|' . $e['source'];
            if (!isset($unique[$key])) {
                $parts = explode('@', $e['email']);
                $domain = $parts[1] ?? '';
                $mx = $domain ? self::_lookup_mx($domain) : [];
                $e['mx_ok'] = !empty($mx);
                $unique[$key] = $e;
            }
        }

        return array_values($unique);
    }

    public static function send_test(string $to): array {
        if (!self::_pro()) return ['ok' => false, 'error' => 'Pro license required.'];
        if (!is_email($to)) return ['ok' => false, 'error' => 'Invalid recipient email.'];

        $errors = [];
        $listener = function($wp_error) use (&$errors) {
            $errors[] = $wp_error->get_error_message();
        };
        add_action('wp_mail_failed', $listener);

        $subject = '[phpinfo() WP] Mail deliverability test';
        $body    = "This is a test email sent at " . current_time('mysql', true) . " UTC\n"
                 . "From: " . get_option('admin_email') . "\n"
                 . "Site: " . get_site_url();
        $ok = wp_mail($to, $subject, $body);

        remove_action('wp_mail_failed', $listener);

        return [
            'ok'       => $ok && !$errors,
            'sent_to'  => $to,
            'errors'   => $errors,
            'method'   => self::_detect_smtp_plugin() ?: 'PHP mail()',
        ];
    }
}
