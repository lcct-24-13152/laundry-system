<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../PHPMailer/src/Exception.php";
require_once __DIR__ . "/../PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/../PHPMailer/src/SMTP.php";
require_once __DIR__ . "/../config/bootstrap.php";

/*
    CML Laundry Stock Alert Email
    Sends email notification to the owner when an item is LOW or CRITICAL.
*/

function sendStockAlert($stock_items){

    if(empty($stock_items) || MAIL_USER === '' || MAIL_PASS === ''){
        return false; // nothing to send, or email not set up in config/config.php
    }

    $mail = new PHPMailer(true);

    try{

        // Set these in config/config.php
        $senderEmail = MAIL_USER;
        $senderPassword = MAIL_PASS;
        $ownerEmail = ALERT_EMAIL !== '' ? ALERT_EMAIL : MAIL_USER;

        $mail->isSMTP();
        $mail->Host       = "smtp.gmail.com";
        $mail->SMTPAuth   = true;
        $mail->Username   = $senderEmail;
        $mail->Password   = $senderPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->CharSet = "UTF-8";
        $mail->Encoding = "base64";

        $mail->setFrom($senderEmail, MAIL_FROM_NAME);
        $mail->addAddress($ownerEmail, "CML Laundry Owner");

        $mail->isHTML(true);
        $mail->Subject = "CML Laundry Stock Alert - Low or Critical Supplies";

        $itemsHtml = "";

        foreach($stock_items as $item){

            $itemName      = htmlspecialchars($item['item_name'] ?? '');
            $category      = htmlspecialchars($item['category'] ?? '');
            $quantity      = htmlspecialchars($item['quantity'] ?? '0');
            $unit          = htmlspecialchars($item['unit'] ?? '');
            $criticalLevel = htmlspecialchars($item['critical_level'] ?? '0');
            $reorderLevel  = htmlspecialchars($item['reorder_level'] ?? '0');

            $quantityNum = floatval($item['quantity'] ?? 0);
            $criticalNum = floatval($item['critical_level'] ?? 0);
            $reorderNum  = floatval($item['reorder_level'] ?? 0);

            /*
                STATUS LOGIC:
                - CRITICAL kapag quantity <= critical_level
                - LOW kapag quantity <= reorder_level
                - OK kapag above reorder level
            */

            if($quantityNum <= $criticalNum){
                $status = "CRITICAL STOCK";
                $badgeColor = "#dc2626";
                $rowBg = "#fef2f2";
            }
            elseif($quantityNum <= $reorderNum){
                $status = "LOW STOCK";
                $badgeColor = "#f59e0b";
                $rowBg = "#fffbeb";
            }
            else{
                $status = "OK";
                $badgeColor = "#16a34a";
                $rowBg = "#f0fdf4";
            }

            $itemsHtml .= "
                <tr style='background:{$rowBg};'>
                    <td style='padding:10px;border:1px solid #e5e7eb;'>{$itemName}</td>
                    <td style='padding:10px;border:1px solid #e5e7eb;'>{$category}</td>
                    <td style='padding:10px;border:1px solid #e5e7eb;text-align:center;'>{$quantity} {$unit}</td>
                    <td style='padding:10px;border:1px solid #e5e7eb;text-align:center;'>{$reorderLevel}</td>
                    <td style='padding:10px;border:1px solid #e5e7eb;text-align:center;'>{$criticalLevel}</td>
                    <td style='padding:10px;border:1px solid #e5e7eb;text-align:center;'>
                        <span style='background:{$badgeColor};color:white;padding:6px 10px;border-radius:20px;font-size:12px;font-weight:bold;display:inline-block;'>
                            {$status}
                        </span>
                    </td>
                </tr>
            ";
        }

        $mail->Body = "
        <div style='font-family:Arial, sans-serif;background:#f8fafc;padding:25px;'>

            <div style='max-width:780px;margin:auto;background:white;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 8px 24px rgba(15,23,42,0.08);'>

                <div style='background:#2563eb;color:white;padding:20px;text-align:center;'>
                    <h2 style='margin:0;font-size:22px;'>CML Laundry Management System</h2>
                    <p style='margin:6px 0 0;font-size:14px;'>Stock Alert Notification</p>
                </div>

                <div style='padding:22px;'>

                    <p style='font-size:15px;color:#334155;margin-top:0;'>
                        Hello Owner,
                    </p>

                    <p style='font-size:15px;color:#334155;line-height:1.6;'>
                        The following laundry supplies are currently marked as 
                        <b>LOW STOCK</b> or <b>CRITICAL STOCK</b>.
                        Please check your inventory and restock as soon as possible.
                    </p>

                    <table style='width:100%;border-collapse:collapse;margin-top:18px;font-size:14px;'>
                        <thead>
                            <tr style='background:#f1f5f9;'>
                                <th style='padding:10px;border:1px solid #e5e7eb;text-align:left;'>Item</th>
                                <th style='padding:10px;border:1px solid #e5e7eb;text-align:left;'>Category</th>
                                <th style='padding:10px;border:1px solid #e5e7eb;text-align:center;'>Current Stock</th>
                                <th style='padding:10px;border:1px solid #e5e7eb;text-align:center;'>Reorder Level</th>
                                <th style='padding:10px;border:1px solid #e5e7eb;text-align:center;'>Critical Level</th>
                                <th style='padding:10px;border:1px solid #e5e7eb;text-align:center;'>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            {$itemsHtml}
                        </tbody>
                    </table>

                    <div style='margin-top:20px;padding:14px;background:#eff6ff;border-left:4px solid #2563eb;border-radius:10px;'>
                        <p style='margin:0;font-size:13px;color:#1e3a8a;line-height:1.5;'>
                            <b>Reminder:</b> Please restock the supplies before they affect laundry operations.
                        </p>
                    </div>

                    <p style='font-size:13px;color:#64748b;margin-top:20px;'>
                        This is an automated notification from CML Laundry Management System.
                    </p>

                </div>

            </div>

        </div>
        ";

        $plainItems = "";

        foreach($stock_items as $item){
            $itemName = $item['item_name'] ?? '';
            $quantity = $item['quantity'] ?? '0';
            $unit = $item['unit'] ?? '';
            $criticalLevel = $item['critical_level'] ?? '0';
            $reorderLevel = $item['reorder_level'] ?? '0';

            $quantityNum = floatval($item['quantity'] ?? 0);
            $criticalNum = floatval($item['critical_level'] ?? 0);
            $reorderNum  = floatval($item['reorder_level'] ?? 0);

            if($quantityNum <= $criticalNum){
                $status = "CRITICAL STOCK";
            }
            elseif($quantityNum <= $reorderNum){
                $status = "LOW STOCK";
            }
            else{
                $status = "OK";
            }

            $plainItems .= "- {$itemName}: {$quantity} {$unit} | Reorder: {$reorderLevel} | Critical: {$criticalLevel} | Status: {$status}\n";
        }

        $mail->AltBody = "CML Laundry Stock Alert\n\nThe following supplies are low or critical:\n\n" . $plainItems;

        $mail->send();

        error_log("Stock alert email sent successfully.");
        return true;

    }catch(Exception $e){

        error_log("Stock alert email failed: " . $mail->ErrorInfo);
        return false;

    }
}
?>