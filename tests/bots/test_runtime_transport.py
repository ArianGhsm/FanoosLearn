import tempfile,unittest
from pathlib import Path
from fanoos_bot.capabilities import TELEGRAM,BALE
from fanoos_bot.models import Screen,ActionResult
from fanoos_bot.runtime import BotRuntime,NotificationPump,UpdateContext
from fanoos_bot.state import LocalState
class RecordingTransport:
    def __init__(self):self.events=[];self.n=0
    def answer_callback(self,c):self.events.append(('ack',c))
    def send_screen(self,chat,s):self.events.append(('send',s.protect_content,s.text));self.n+=1;return {'message_id':self.n}
    def edit_screen(self,chat,msg,s):self.events.append(('edit',s.text));return {'message_id':msg}
class App:
    def __init__(self):self.backend=self
    def callback(self,*a):return ActionResult(Screen('done'))
    def home(self,*a):return ActionResult(Screen('home'))
    def help(self):return ActionResult(Screen('help'))
    def notification_receipt(self,*a):self.receipt=a
class Backend:
    def __init__(self):self.claimed=False;self.receipts=[]
    def claim_notification(self,p):
        if self.claimed:return {'delivery':None}
        self.claimed=True;return {'delivery':{'delivery_id':'d1','lease_token':'l','subject':'42','payload':{'title':'T','body':'B'}}}
    def notification_receipt(self,*a):self.receipts.append(a)
class RuntimeTest(unittest.TestCase):
    def test_telegram_protection_serializable(self):self.assertTrue(TELEGRAM.supports_forward_protection)
    def test_bale_no_protection_capability(self):self.assertFalse(BALE.supports_forward_protection)
    def test_callback_ack_precedes_send(self):
        t=RecordingTransport();a=App();s=tempfile.TemporaryDirectory();st=LocalState(Path(s.name)/'s');r=BotRuntime('telegram',t,a,st);r.handle_callback(UpdateContext('1','1',True,1,'cb','9'),'home');self.assertEqual(t.events[0],('ack','cb'));st.close();s.cleanup()
    def test_notification_restart_dedupe_receipt(self):
        s=tempfile.TemporaryDirectory();st=LocalState(Path(s.name)/'s');b=Backend();t=RecordingTransport();p=NotificationPump('telegram',b,t,st);self.assertTrue(p.run_once());self.assertEqual(len(b.receipts),1);st.close();s.cleanup()
