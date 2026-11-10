"""Use official subscription five-hour and weekly percentages; no token-estimate surrogate."""
import datetime as dt
import json
import pathlib
import sys
import unittest
ROOT=pathlib.Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/'scripts/classification_v3'))
from quota_guard import parse_status,check

NOW=dt.datetime(2026,11,10,5,30,tzinfo=dt.timezone.utc)
def status(five,weekly):
    raw={'type':'result','is_error':False,'total_cost_usd':0,
      'result':('You are currently using your subscription to power your Claude Code usage\n'
      f'Current session: {five}% used · resets Nov 10, 6:10am (UTC)\n'
      f'Current week (all models): {weekly}% used · resets Nov 15, 11am (UTC)\n'
      'Current week (Fable): 0% used · resets Nov 15, 11am (UTC)\n')}
    return parse_status(json.dumps(raw),NOW)

class QuotaGuardTests(unittest.TestCase):
 def test_true_subscription_values_not_api_cost(self):
  d=status(31,16)
  self.assertEqual(d['five_hour_pct'],31)
  self.assertEqual(d['week_all_pct'],16)
  self.assertEqual(d['resets_utc']['five_hour'],'2026-11-10T06:10:00+00:00')
  self.assertTrue(check(d)[0])
 def test_session_reserve_threshold(self):
  self.assertTrue(check(status(74,16))[0])
  ok,errors=check(status(75,16))
  self.assertFalse(ok)
  self.assertIn('five_hour_headroom_reserved',errors)
 def test_weekly_reserve_threshold(self):
  self.assertTrue(check(status(25,79))[0])
  self.assertFalse(check(status(25,80))[0])
 def test_fail_closed_missing_bar(self):
  with self.assertRaises(ValueError):
   parse_status(json.dumps({'type':'result','is_error':False,
          'total_cost_usd':0,'result':'Current session: 12% used · resets Nov 10, 6:10am (UTC)'}),NOW)
 def test_fail_closed_if_command_spent_tokens(self):
  with self.assertRaises(ValueError):
   parse_status(json.dumps({'type':'result','is_error':False,
      'total_cost_usd':0.03,'result':"Current session: 23% used · resets Nov 10, 6:10am (UTC)\nCurrent week (all models): 35% used · resets Nov 15, 11am (UTC)"}),NOW)
 def test_wrong_pct(self):
  with self.assertRaises(ValueError): status(105,16)
 def test_year_rollover(self):
  now=dt.datetime(2026,12,31,23,30,tzinfo=dt.timezone.utc)
  raw={'type':'result','total_cost_usd':0,'result':'Current session: 23% used · resets Jan 1, 1:10am (UTC)\nCurrent week (all models): 35% used · resets Jan 2, 11am (UTC)'}
  d=parse_status(json.dumps(raw),now)
  self.assertEqual(d['resets_utc']['five_hour'],'2027-01-01T01:10:00+00:00')
if __name__=='__main__':unittest.main()
