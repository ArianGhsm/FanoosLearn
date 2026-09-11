(function () {
  "use strict";

  if (!window.Dent1402Auth) {
    return;
  }

  document.documentElement.classList.add("chat-page-root");

  var QUICK_REACTIONS = [
    "\u{1F44D}", "\u2764\uFE0F", "\u{1F602}", "\u{1F525}", "\u{1F44F}",
    "\u{1F62E}", "\u{1F44E}", "\u{1F60D}", "\u{1F914}", "\u{1F389}",
    "\u{1F44C}", "\u{1F64F}"
  ];
  var REACTION_LIBRARY = [
    "\u{1F44D}", "\u{1F44E}", "\u2764\uFE0F", "\u{1F9E1}", "\u{1F49A}", "\u{1F499}",
    "\u{1F49C}", "\u{1F5A4}", "\u{1F4AF}", "\u{1F525}", "\u{1F389}", "\u{1F4A5}",
    "\u{1F44F}", "\u{1F64F}", "\u{1F64C}", "\u{1F44C}", "\u{1F91D}", "\u{1F4AA}",
    "\u{1F680}", "\u{1F6A8}", "\u{1F600}", "\u{1F603}", "\u{1F604}", "\u{1F601}",
    "\u{1F606}", "\u{1F605}", "\u{1F923}", "\u{1F602}", "\u{1F642}", "\u{1F60D}",
    "\u{1F970}", "\u{1F60E}", "\u{1F914}", "\u{1F609}", "\u{1F62E}", "\u{1F632}",
    "\u{1F621}", "\u{1F622}", "\u{1F62D}", "\u{1F97A}", "\u{1F44A}", "\u{1F44B}",
    "\u{1F31F}", "\u{1F308}", "\u{1F3AF}", "\u{1F3C6}", "\u{1F91F}", "\u{270C}\uFE0F",
    "\u{1F91E}", "\u{1F90C}", "\u{1F937}", "\u{1F90D}", "\u{1F607}", "\u{1F915}",
    "\u{1F973}", "\u{1F92F}", "\u{1FAE1}", "\u{1FAE0}", "\u{1FAE2}", "\u{1F90D}",
    "\u{1F618}", "\u{1F61A}", "\u{1F61C}", "\u{1F61D}", "\u{1F910}", "\u{1F917}",
    "\u{1F92D}", "\u{1F976}", "\u{1F975}", "\u{1F637}", "\u{1F631}", "\u{1F62F}",
    "\u{1F633}", "\u{1F62B}", "\u{1F62A}", "\u{1F644}", "\u{1F643}", "\u{1F60F}",
    "\u{1F611}", "\u{1F610}", "\u{1F612}", "\u{1F914}", "\u{1F90F}", "\u{1F919}",
    "\u{1F596}", "\u{1F450}", "\u{1FAF6}", "\u{1F90C}", "\u{1F927}", "\u{1F974}",
    "\u{1F436}", "\u{1F431}", "\u{1F43C}", "\u{1F98A}", "\u{1F981}", "\u{1F984}",
    "\u{1F42F}", "\u{1F437}", "\u{1F98B}", "\u{1F355}", "\u{1F354}", "\u{1F356}",
    "\u{1F35F}", "\u{1F370}", "\u{1F369}", "\u{1F366}", "\u{1F37F}", "\u{1F95E}",
    "\u{1F964}", "\u{2615}", "\u{1F37A}", "\u{1F9CB}", "\u{1F3C1}", "\u{1F3C5}",
    "\u{1F3C6}", "\u{1F947}", "\u{1F948}", "\u{1F949}", "\u{1F451}", "\u{1F4A1}",
    "\u{1F4AA}", "\u{26A1}", "\u{2600}\uFE0F", "\u{1F319}", "\u{2744}\uFE0F", "\u{2B50}",
    "\u{1F680}", "\u{1F6F8}", "\u{1F6E1}\uFE0F", "\u{1F4E2}", "\u{1F4CC}", "\u{1F4A3}"
  ];
  var REACTIONS = Array.from(new Set(QUICK_REACTIONS.concat(REACTION_LIBRARY)));
  var TELEGRAM_HEART_REACTION = "\u2764\uFE0F";
  var MESSAGE_DOUBLE_TAP_WINDOW_MS = 300;
  var MESSAGE_DOUBLE_TAP_MOVE_PX = 26;
  var CHAT_PRESENCE_HEARTBEAT_MS = 25000;
  var CHAT_TYPING_IDLE_MS = 3400;
  var CHAT_TYPING_REFRESH_MS = 2200;
  var CHAT_STREAM_RETRY_MS = 1600;
  var THREAD_SEARCH_DEBOUNCE_MS = 220;
  var THREAD_SEARCH_RESULT_LIMIT = 80;
  var THREAD_CONTEXT_BEFORE_LIMIT = 36;
  var THREAD_CONTEXT_AFTER_LIMIT = 24;
  var VOICE_GESTURE_CANCEL_PX = 84;
  var VOICE_GESTURE_LOCK_PX = 72;
  var VOICE_WAVE_BAR_COUNT = 24;
  var VOICE_PLAYBACK_SPEEDS = [1, 1.5, 2];
  var pageCohort = "main";
  var chatHomePath = "/chat/";
  var REACTION_GROUPS = [
    { id: "recent", label: "اخیر", emojis: [] },
    { id: "popular", label: "پرکاربرد", emojis: QUICK_REACTIONS.slice() },
    {
      id: "smileys",
      label: "حالت‌ها",
      emojis: ["\u{1F600}", "\u{1F603}", "\u{1F604}", "\u{1F601}", "\u{1F606}", "\u{1F602}", "\u{1F923}", "\u{1F642}", "\u{1F60D}", "\u{1F609}", "\u{1F914}", "\u{1F92F}", "\u{1F62E}", "\u{1F622}", "\u{1F62D}"]
    },
    {
      id: "gestures",
      label: "ژست‌ها",
      emojis: ["\u{1F44D}", "\u{1F44E}", "\u{1F44F}", "\u{1F64F}", "\u{1F64C}", "\u{1F44C}", "\u{1F91D}", "\u{1F4AA}", "\u{270C}\uFE0F", "\u{1F91F}", "\u{1F90C}", "\u{1F44A}", "\u{1F44B}"]
    },
    {
      id: "hearts",
      label: "قلب",
      emojis: ["\u2764\uFE0F", "\u{1F9E1}", "\u{1F49A}", "\u{1F499}", "\u{1F49C}", "\u{1F5A4}", "\u{1F970}", "\u{1F90D}"]
    },
    {
      id: "symbols",
      label: "نمادها",
      emojis: ["\u{1F4AF}", "\u{1F525}", "\u{1F389}", "\u{1F4A5}", "\u{1F680}", "\u{1F31F}", "\u{1F308}", "\u{1F3AF}", "\u{1F3C6}", "\u{1F607}", "\u{1F973}"]
    },
    {
      id: "nature",
      label: "طبیعت",
      emojis: ["\u{2600}\uFE0F", "\u{1F319}", "\u{2B50}", "\u{1F308}", "\u{1F525}", "\u{2744}\uFE0F", "\u{1F436}", "\u{1F431}", "\u{1F43C}", "\u{1F98A}"]
    },
    {
      id: "food",
      label: "خوراکی",
      emojis: ["\u{1F355}", "\u{1F354}", "\u{1F35F}", "\u{1F95E}", "\u{1F37F}", "\u{1F370}", "\u{1F369}", "\u{1F366}", "\u{2615}", "\u{1F37A}"]
    },
    {
      id: "activity",
      label: "فعالیت",
      emojis: ["\u{1F3AF}", "\u{1F3C6}", "\u{1F3C1}", "\u{1F3C5}", "\u{1F947}", "\u{1F948}", "\u{1F949}", "\u{1F4AA}", "\u{1F389}", "\u{1F38A}"]
    }
  ];
  var REACTION_SEARCH_ALIASES = {
    "\u{1F44D}": ["thumbs up", "like", "good", "ok", "عالی", "خوبه", "تایید"],
    "\u{1F44E}": ["thumbs down", "dislike", "bad", "not ok", "بد", "نپسندیدن"],
    "\u2764\uFE0F": ["heart", "love", "care", "عشق", "دوستت دارم", "قلب"],
    "\u{1F602}": ["laugh", "lol", "funny", "خنده", "باحال", "خنده‌دار"],
    "\u{1F62D}": ["cry", "sad", "tears", "غم", "ناراحت", "گریه"],
    "\u{1F60D}": ["love eyes", "adore", "wow", "عاشق", "عالیه", "واو"],
    "\u{1F914}": ["thinking", "hmm", "consider", "فکر", "نامطمئن", "مردد"],
    "\u{1F62E}": ["surprised", "wow", "shock", "تعجب", "شوکه", "وااا"],
    "\u{1F525}": ["fire", "lit", "hot", "آتیش", "خفن", "داغ"],
    "\u{1F389}": ["party", "celebrate", "congrats", "تبریک", "جشن", "آفرین"],
    "\u{1F4AF}": ["hundred", "perfect", "full", "صد", "کامل", "درسته"],
    "\u{1F64F}": ["pray", "please", "thanks", "دعا", "ممنون", "سپاس"],
    "\u{1F44F}": ["clap", "applause", "bravo", "دست", "تشویق", "آفرین"],
    "\u{1F44C}": ["ok hand", "perfect", "nice", "اوکی", "خوبه", "اوک"],
    "\u{1F90C}": ["pinched fingers", "wait", "چی", "صبر", "یعنی چی", "حرف حساب"],
    "\u{1F44A}": ["punch", "fight", "power", "مشت", "قدرت", "بزن بریم"],
    "\u{1F44B}": ["wave", "hello", "bye", "سلام", "خداحافظ", "دست تکان"],
    "\u{1F31F}": ["glow", "star", "shine", "درخشان", "ستاره", "نور"],
    "\u{1F308}": ["rainbow", "color", "hope", "رنگی", "رنگین‌کمان", "امید"],
    "\u{1F3AF}": ["target", "focus", "goal", "هدف", "مستقیم", "درست"],
    "\u{1F3C6}": ["trophy", "win", "champion", "برد", "قهرمان", "جام"],
    "\u{1F9E1}": ["orange heart", "warm", "care", "قلب نارنجی", "محبت", "دوستی"],
    "\u{1F49A}": ["green heart", "peace", "nature", "قلب سبز", "آرامش", "طبیعت"],
    "\u{1F499}": ["blue heart", "trust", "calm", "قلب آبی", "اعتماد", "آرام"],
    "\u{1F49C}": ["purple heart", "support", "kind", "قلب بنفش", "حمایت", "مهربانی"],
    "\u{1F5A4}": ["black heart", "dark", "bold", "قلب سیاه", "خاص", "سنگین"],
    "\u{1F970}": ["smiling heart", "affection", "sweet", "مهربان", "لطیف", "محبت"],
    "\u{1F90D}": ["white heart", "pure", "clean", "قلب سفید", "پاک", "بی‌ریا"],
    "\u{1F4AA}": ["muscle", "strong", "power", "قدرت", "قوی", "توان"],
    "\u{1F680}": ["rocket", "launch", "fast", "موشک", "شروع", "سریع"],
    "\u{1F6A8}": ["alarm", "warning", "alert", "هشدار", "اخطار", "توجه"],
    "\u{1F637}": ["mask", "sick", "health", "ماسک", "بیماری", "سلامت"],
    "\u{1F631}": ["scream", "panic", "shock", "وحشت", "ترس", "واکنش شدید"],
    "\u{1F973}": ["party face", "celebrate", "yay", "جشن", "خوشحالی", "تبریک"],
    "\u{1F97A}": ["teary", "emotional", "moved", "احساسی", "اشک شوق", "تحت تاثیر"],
    "\u{1F355}": ["pizza", "food", "snack", "پیتزا", "غذا", "خوراکی"],
    "\u{1F354}": ["burger", "food", "meal", "برگر", "ساندویچ", "غذا"],
    "\u{2615}": ["coffee", "tea", "break", "قهوه", "چای", "استراحت"],
    "\u{1F37A}": ["drink", "cheers", "party", "نوشیدنی", "به سلامتی", "جشن"],
    "\u{1F436}": ["dog", "pet", "animal", "سگ", "حیوان", "پت"],
    "\u{1F431}": ["cat", "pet", "animal", "گربه", "حیوان", "پت"],
    "\u{1F43C}": ["panda", "cute", "animal", "پاندا", "بامزه", "حیوان"],
    "\u{1F98A}": ["fox", "smart", "animal", "روباه", "باهوش", "حیوان"],
    "\u{2600}\uFE0F": ["sun", "bright", "day", "آفتاب", "روشن", "روز"],
    "\u{1F319}": ["moon", "night", "sleep", "ماه", "شب", "خواب"],
    "\u{2744}\uFE0F": ["snow", "cold", "winter", "برف", "سرد", "زمستان"],
    "\u{2B50}": ["star", "favorite", "special", "ستاره", "موردعلاقه", "خاص"]
  };
  var MAX_MESSAGE_SIZE = 2000;
  var MAX_MEDIA_BYTES = 25 * 1024 * 1024;
  var INITIAL_MESSAGE_LIMIT = 80;
  var OLDER_MESSAGE_LIMIT = 60;
  var MIN_POLL_MS = 1000;
  var MAX_POLL_MS = 8000;
  var POLL_MIN_OPTIONS = 2;
  var POLL_MAX_OPTIONS = 10;
  var REACTION_RECENTS_LIMIT = 24;
  var REACTION_USAGE_LIMIT = 120;
  var CHAT_FAST_CACHE_VERSION = 1;
  var CHAT_FAST_CACHE_TTL_MS = 7 * 24 * 60 * 60 * 1000;
  var CHAT_FAST_CACHE_CONVERSATION_LIMIT = 160;
  var CHAT_FAST_CACHE_MESSAGE_LIMIT = 140;
  var CHAT_DRAFT_TTL_MS = 30 * 24 * 60 * 60 * 1000;
  var CHAT_DRAFT_SAVE_DELAY_MS = 180;
  var EMOJI_CATEGORIES = [
    {
      key: "smileys",
      icon: "😀",
      label: "صورت‌ها و احساسات",
      items: ["😀", "😃", "😄", "😁", "😆", "😅", "🤣", "😂", "🙂", "🙃", "😉", "😊", "😇", "🥰", "😍", "🤩", "😘", "😗", "😚", "😙", "😋", "😛", "😝", "😜", "🤪", "🤔", "🤨", "😐", "😑", "😶", "😏", "😒", "🙄", "😬", "🤥", "😌", "😔", "😪", "🤤", "😴", "😷", "🤒", "🤕", "🤢", "🤮", "🤧", "🥵", "🥶", "🥴", "😵", "🤯", "🤠", "🥳", "🥸", "😎", "🤓", "🧐", "😕", "😟", "🙁", "☹️", "😮", "😯", "😲", "😳", "🥺", "😦", "😧", "😨", "😰", "😥", "😢", "😭", "😱", "😖", "😣", "😞", "😓", "😩", "😫", "🥱", "😤", "😡", "😠", "🤬", "😈", "👿", "💀", "☠️", "💩", "🤡", "👹", "👺", "👻", "👽", "👾", "🤖", "😺", "😸", "😹", "😻", "😼", "😽", "🙀", "😿", "😾", "🥹"]
    },
    {
      key: "gestures",
      icon: "👋",
      label: "حالت‌ها و افراد",
      items: ["👋", "🤚", "🖐️", "✋", "🖖", "🫱", "🫲", "🫳", "🫴", "👌", "🤌", "🤏", "✌️", "🤞", "🫰", "🤟", "🤘", "🤙", "👈", "👉", "👆", "🖕", "👇", "☝️", "👍", "👎", "✊", "👊", "🤛", "🤜", "👏", "🙌", "🫶", "👐", "🤲", "🙏", "✍️", "💅", "🤳", "💪", "🦾", "🦵", "🦿", "🦶", "👂", "🦻", "👃", "🧠", "🦷", "🦴", "👀", "👁️", "👅", "👄", "🫦", "👶", "🧒", "👦", "👧", "🧑", "👱", "👨", "🧔", "👩", "🧓", "👴", "👵", "🙍", "🙎", "🙅", "🙆", "💁", "🙋", "🧏", "🙇", "🤦", "🤷", "👮", "🕵️", "💂", "🥷", "👷", "🤴", "👸", "👳", "👲", "🧕", "🤵", "👰", "🤰", "🤱", "👼", "🎅", "🤶", "🦸", "🦹", "🧙", "🧚", "🧛", "🧜", "🧝", "🧞", "🧟", "💆", "💇", "🚶", "🧍", "🧎", "🏃", "💃", "🕺", "👯", "🧖", "👭", "👫", "👬"]
    },
    {
      key: "hearts",
      icon: "❤️",
      label: "قلب‌ها",
      items: ["❤️", "🧡", "💛", "💚", "💙", "💜", "🖤", "🤍", "🤎", "💔", "❤️‍🔥", "❤️‍🩹", "❣️", "💕", "💞", "💓", "💗", "💖", "💘", "💝", "💟", "♥️", "💌", "💋", "💯", "💢", "💥", "💫", "💦", "💨", "💬", "👁️‍🗨️", "🗨️", "🗯️", "💭", "💤"]
    },
    {
      key: "animals",
      icon: "🐶",
      label: "حیوانات و طبیعت",
      items: ["🐶", "🐱", "🐭", "🐹", "🐰", "🦊", "🐻", "🐼", "🐨", "🐯", "🦁", "🐮", "🐷", "🐽", "🐸", "🐵", "🙈", "🙉", "🙊", "🐒", "🐔", "🐧", "🐦", "🐤", "🐣", "🐥", "🦆", "🦅", "🦉", "🦇", "🐺", "🐗", "🐴", "🦄", "🐝", "🪱", "🐛", "🦋", "🐌", "🐞", "🐜", "🪰", "🪲", "🪳", "🦟", "🦗", "🕷️", "🕸️", "🦂", "🐢", "🐍", "🦎", "🦖", "🦕", "🐙", "🦑", "🦐", "🦞", "🦀", "🐡", "🐠", "🐟", "🐬", "🐳", "🐋", "🦈", "🐊", "🐅", "🐆", "🦓", "🦍", "🦧", "🦣", "🐘", "🦛", "🦏", "🐪", "🐫", "🦒", "🦘", "🦬", "🐃", "🐂", "🐄", "🐎", "🐖", "🐏", "🐑", "🦙", "🐐", "🦌", "🐕", "🐩", "🦮", "🐈", "🐓", "🦃", "🦤", "🦚", "🦜", "🦢", "🦩", "🕊️", "🐇", "🦝", "🦨", "🦡", "🦫", "🦦", "🦥", "🐁", "🐀", "🐿️", "🦔", "🐾", "🐉", "🐲", "🌵", "🎄", "🌲", "🌳", "🌴", "🪵", "🌱", "🌿", "☘️", "🍀", "🎍", "🪴", "🎋", "🍃", "🍂", "🍁", "🍄", "🐚", "🪨", "🌾", "💐", "🌷", "🪻", "🌹", "🥀", "🌺", "🌸", "🌼", "🌻", "🌞", "🌝", "🌛", "🌜", "🌚", "🌕", "🌖", "🌗", "🌘", "🌑", "🌒", "🌓", "🌔", "🌙", "🌎", "🌍", "🌏", "🪐", "⭐", "🌟", "✨", "⚡", "☄️", "🔥", "🌪️", "🌈", "☀️", "🌤️", "⛅", "🌥️", "☁️", "🌦️", "🌧️", "⛈️", "🌩️", "🌨️", "❄️", "☃️", "⛄", "🌬️", "☔", "☂️", "🌊", "🌫️"]
    },
    {
      key: "food",
      icon: "🍔",
      label: "غذا و نوشیدنی",
      items: ["🍏", "🍎", "🍐", "🍊", "🍋", "🍌", "🍉", "🍇", "🍓", "🫐", "🍈", "🍒", "🍑", "🥭", "🍍", "🥥", "🥝", "🍅", "🍆", "🥑", "🥦", "🥬", "🥒", "🌶️", "🫑", "🌽", "🥕", "🫒", "🧄", "🧅", "🥔", "🍠", "🥐", "🥯", "🍞", "🥖", "🥨", "🧀", "🥚", "🍳", "🧈", "🥞", "🧇", "🥓", "🥩", "🍗", "🍖", "🌭", "🍔", "🍟", "🍕", "🫓", "🥪", "🌮", "🌯", "🫔", "🥙", "🧆", "🥘", "🍲", "🫕", "🥣", "🥗", "🍿", "🧂", "🥫", "🍱", "🍘", "🍙", "🍚", "🍛", "🍜", "🍝", "🍢", "🍣", "🍤", "🍥", "🥮", "🍡", "🥟", "🥠", "🥡", "🦀", "🦞", "🦐", "🦑", "🦪", "🍦", "🍧", "🍨", "🍩", "🍪", "🎂", "🍰", "🧁", "🥧", "🍫", "🍬", "🍭", "🍮", "🍯", "🍼", "🥛", "☕", "🫖", "🍵", "🍶", "🍾", "🍷", "🍸", "🍹", "🍺", "🍻", "🥂", "🥃", "🥤", "🧋", "🧃", "🧉", "🧊", "🥢", "🍽️", "🍴", "🥄"]
    },
    {
      key: "activities",
      icon: "⚽",
      label: "فعالیت‌ها و ورزش",
      items: ["⚽", "🏀", "🏈", "⚾", "🥎", "🎾", "🏐", "🏉", "🥏", "🎱", "🪀", "🏓", "🏸", "🏒", "🏑", "🥍", "🏏", "🪃", "🥅", "⛳", "🪁", "🏹", "🎣", "🤿", "🥊", "🥋", "🎽", "🛹", "🛼", "🛷", "⛸️", "🥌", "🎿", "🏂", "🪂", "🏋️", "🤼", "🤸", "⛹️", "🤺", "🤾", "🏌️", "🏇", "🧘", "🏄", "🏊", "🤽", "🚣", "🧗", "🚴", "🚵", "🎯", "🎳", "🎮", "🎰", "🧩", "♟️", "🎲", "🎭", "🩰", "🎨", "🎬", "🎤", "🎧", "🎼", "🎹", "🥁", "🪘", "🎷", "🎺", "🪗", "🎸", "🪕", "🎻", "🏆", "🥇", "🥈", "🥉", "🏅", "🎖️", "🏵️", "🎗️", "🎫", "🎟️", "🎪", "🤹", "🪄", "🎊", "🎉", "🎈", "🎀", "🎁", "🪅", "🪆", "🧸", "🪩"]
    },
    {
      key: "travel",
      icon: "🚗",
      label: "سفر و مکان‌ها",
      items: ["🚗", "🚕", "🚙", "🚌", "🚎", "🏎️", "🚓", "🚑", "🚒", "🚐", "🛻", "🚚", "🚛", "🚜", "🦽", "🦼", "🛴", "🚲", "🛵", "🏍️", "🛺", "🚨", "🚔", "🚍", "🚘", "🚖", "🚡", "🚠", "🚟", "🚃", "🚋", "🚞", "🚝", "🚄", "🚅", "🚈", "🚂", "🚆", "🚇", "🚊", "🚉", "✈️", "🛫", "🛬", "🛩️", "💺", "🛰️", "🚀", "🛸", "🚁", "🛶", "⛵", "🚤", "🛥️", "🛳️", "⛴️", "🚢", "⚓", "🪝", "⛽", "🚧", "🚦", "🚥", "🚏", "🗺️", "🧭", "🗿", "🗽", "🗼", "🏰", "🏯", "🏟️", "🎡", "🎢", "🎠", "⛲", "⛱️", "🏖️", "🏝️", "🏜️", "🌋", "⛰️", "🏔️", "🗻", "🏕️", "⛺", "🏠", "🏡", "🏘️", "🏚️", "🏗️", "🏭", "🏢", "🏬", "🏣", "🏤", "🏥", "🏦", "🏨", "🏪", "🏫", "🏩", "💒", "🏛️", "⛪", "🕌", "🕍", "🛕", "🕋", "⛩️", "🌁", "🌃", "🏙️", "🌄", "🌅", "🌆", "🌇", "🌉", "♨️", "🎑"]
    },
    {
      key: "objects",
      icon: "💡",
      label: "اشیاء و وسایل",
      items: ["⌚", "📱", "📲", "💻", "⌨️", "🖥️", "🖨️", "🖱️", "🖲️", "🕹️", "💽", "💾", "💿", "📀", "📼", "📷", "📸", "📹", "🎥", "📽️", "🎞️", "📞", "☎️", "📟", "📠", "📺", "📻", "🎙️", "🎚️", "🎛️", "⏱️", "⏲️", "⏰", "🕰️", "⌛", "⏳", "📡", "🔋", "🪫", "🔌", "💡", "🔦", "🕯️", "🪔", "🧯", "🛢️", "💸", "💵", "💴", "💶", "💷", "🪙", "💰", "💳", "🧾", "💎", "⚖️", "🪜", "🧰", "🔧", "🔨", "⚒️", "🛠️", "⛏️", "🔩", "⚙️", "🪤", "🧱", "⛓️", "🧲", "🔫", "💣", "🧨", "🪓", "🔪", "🗡️", "⚔️", "🛡️", "🚬", "⚰️", "🪦", "⚱️", "🔮", "📿", "🧿", "💈", "⚗️", "🔭", "🔬", "🕳️", "🩹", "💊", "💉", "🩸", "🩺", "🌡️", "🚪", "🛗", "🪞", "🛏️", "🛋️", "🚽", "🚿", "🛁", "🪒", "🧴", "🧷", "🧹", "🧺", "🧻", "🪣", "🧼", "🫧", "🪥", "🧽", "🛒", "📦", "📫", "📪", "📬", "📭", "📮", "📯", "📜", "📃", "📄", "📊", "📈", "📉", "🗒️", "🗓️", "📆", "📅", "🗑️", "📇", "🗃️", "🗳️", "🗄️", "📋", "📁", "📂", "🗂️", "🗞️", "📰", "📓", "📔", "📒", "📕", "📗", "📘", "📙", "📚", "📖", "🔖", "🔗", "📎", "🖇️", "📐", "📏", "🧮", "📌", "📍", "✂️", "🖊️", "🖋️", "✒️", "🖌️", "🖍️", "📝", "✏️", "🔍", "🔎", "🔏", "🔐", "🔒", "🔓"]
    },
    {
      key: "symbols",
      icon: "🔣",
      label: "نمادها",
      items: ["💯", "✅", "❌", "❎", "✔️", "☑️", "➕", "➖", "➗", "✖️", "♾️", "💲", "💱", "™️", "©️", "®️", "〰️", "➰", "➿", "🔚", "🔙", "🔛", "🔝", "🔜", "✳️", "✴️", "❇️", "‼️", "⁉️", "❓", "❔", "❕", "❗", "〽️", "⚠️", "🚸", "🔱", "⚜️", "🔰", "♻️", "🈯", "💹", "🌐", "💠", "Ⓜ️", "🌀", "🏧", "🚾", "♿", "🅿️", "🈳", "🈂️", "🛂", "🛃", "🛄", "🛅", "🚹", "🚺", "🚼", "⚧️", "🚻", "🚮", "🎦", "📶", "🈁", "ℹ️", "🔤", "🔡", "🔠", "🆖", "🆗", "🆙", "🆒", "🆕", "🆓", "0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣", "9️⃣", "🔟", "🔢", "#️⃣", "*️⃣", "⏏️", "▶️", "⏸️", "⏯️", "⏹️", "⏺️", "⏭️", "⏮️", "⏩", "⏪", "⏫", "⏬", "◀️", "🔼", "🔽", "➡️", "⬅️", "⬆️", "⬇️", "↗️", "↘️", "↙️", "↖️", "↕️", "↔️", "↪️", "↩️", "⤴️", "⤵️", "🔀", "🔁", "🔂", "🔄", "🔃", "⚛️", "🕉️", "✡️", "☸️", "☯️", "✝️", "☦️", "☪️", "☮️", "🕎", "🔯", "♈", "♉", "♊", "♋", "♌", "♍", "♎", "♏", "♐", "♑", "♒", "♓", "⛎", "🆔", "🆚", "📴", "📳", "🈶", "🈚", "🈸", "🈺", "🈷️", "🉐", "㊙️", "㊗️", "🈴", "🈵", "🈹", "🈲", "🅰️", "🅱️", "🆎", "🆑", "🅾️", "🆘", "🛑", "⛔", "📛", "🚫", "🚷", "🚳", "🚱", "🔞", "📵", "🚭", "🔅", "🔆", "🔴", "🟠", "🟡", "🟢", "🔵", "🟣", "🟤", "⚫", "⚪", "🟥", "🟧", "🟨", "🟩", "🟦", "🟪", "🟫", "⬛", "⬜", "◼️", "◻️", "◾", "◽", "▪️", "▫️", "🔶", "🔷", "🔸", "🔹", "🔺", "🔻", "🔘", "🔳", "🔲"]
    },
    {
      key: "flags",
      icon: "🏳️",
      label: "پرچم‌ها",
      items: ["🏁", "🚩", "🎌", "🏴", "🏳️", "🏳️‍🌈", "🏳️‍⚧️", "🏴‍☠️", "🇮🇷", "🇦🇫", "🇮🇶", "🇸🇦", "🇦🇪", "🇶🇦", "🇰🇼", "🇧🇭", "🇴🇲", "🇾🇪", "🇯🇴", "🇱🇧", "🇸🇾", "🇮🇱", "🇵🇸", "🇪🇬", "🇹🇷", "🇹🇯", "🇺🇿", "🇹🇲", "🇰🇿", "🇰🇬", "🇵🇰", "🇮🇳", "🇧🇩", "🇱🇰", "🇳🇵", "🇨🇳", "🇯🇵", "🇰🇷", "🇰🇵", "🇹🇭", "🇻🇳", "🇲🇾", "🇮🇩", "🇵🇭", "🇸🇬", "🇦🇺", "🇳🇿", "🇷🇺", "🇺🇦", "🇩🇪", "🇫🇷", "🇬🇧", "🇮🇹", "🇪🇸", "🇵🇹", "🇳🇱", "🇧🇪", "🇨🇭", "🇦🇹", "🇸🇪", "🇳🇴", "🇩🇰", "🇫🇮", "🇮🇸", "🇮🇪", "🇵🇱", "🇨🇿", "🇸🇰", "🇭🇺", "🇷🇴", "🇧🇬", "🇬🇷", "🇭🇷", "🇷🇸", "🇺🇸", "🇨🇦", "🇲🇽", "🇧🇷", "🇦🇷", "🇨🇱", "🇨🇴", "🇵🇪", "🇻🇪", "🇿🇦", "🇳🇬", "🇰🇪", "🇪🇹", "🇲🇦", "🇹🇳", "🇩🇿", "🇱🇾"]
    }
  ];
  var VOICE_MIME_CANDIDATES = [
    "audio/webm;codecs=opus",
    "audio/ogg;codecs=opus",
    "audio/mp4"
  ];
  var viewportRefreshFrame = 0;
  var viewportRefreshNeedsComposerSync = false;

  function $(id) {
    return document.getElementById(id);
  }

  function authApi() {
    return asObject(window.Dent1402Auth);
  }

  function resolvePageCohort() {
    var auth = authApi();
    if (auth && typeof auth.resolvePageCohort === "function") {
      return auth.resolvePageCohort("chatCohort");
    }
    return "main";
  }

  function scopedChatPath(path) {
    var auth = authApi();
    var basePath = String(path || "").trim() || "/chat/";
    if (auth && typeof auth.appendCohortQuery === "function") {
      return auth.appendCohortQuery(basePath, pageCohort);
    }
    return basePath;
  }

  function asObject(value) {
    return value && typeof value === "object" && !Array.isArray(value) ? value : null;
  }

  pageCohort = resolvePageCohort();
  chatHomePath = scopedChatPath("/chat/");

  function toText(value) {
    return String(value == null ? "" : value);
  }

  function escapeHtml(value) {
    return toText(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function formatNavBadgeCount(value) {
    var count = Math.max(0, Math.floor(toNumber(value, 0)));
    if (count <= 0) return "";
    if (count > 9) return "۹+";
    return count.toLocaleString("fa-IR");
  }

  function setChatNavBadge(node, count, label) {
    if (!node) return;
    var text = formatNavBadgeCount(count);
    node.hidden = !text;
    node.textContent = text;
    if (text) {
      node.setAttribute("aria-label", label || "مورد جدید");
    } else {
      node.removeAttribute("aria-label");
    }
  }

  function syncMobileNavLinks() {
    if (chatNavHome) {
      chatNavHome.href = scopedChatPath("/app/");
    }
    if (chatNavExams) {
      chatNavExams.href = scopedChatPath("/exams/");
    }
    if (chatNavSettings) {
      chatNavSettings.href = scopedChatPath("/account/");
    }
  }

  function activeUnreadConversationCount() {
    return state.conversations.reduce(function (count, conversation) {
      if (!conversation) return count;
      if (conversation.viewerState && conversation.viewerState.archived) {
        return count;
      }
      return count + Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0)));
    }, 0);
  }

  function emitGlobalUnreadBadge(kind, count) {
    window.dispatchEvent(new CustomEvent("dent1402:" + kind + "-change", {
      detail: {
        unreadCount: Math.max(0, Math.floor(toNumber(count, 0)))
      }
    }));
  }

  function updateChatNavBadges() {
    var unreadCount = state.me.loggedIn ? activeUnreadConversationCount() : 0;
    setChatNavBadge(chatNavListBadge, unreadCount, "پیام خوانده‌نشده");
    setChatNavBadge(chatNavSettingsBadge, state.me.loggedIn ? navBadgeState.notificationsUnread : 0, "اعلان خوانده‌نشده");
    emitGlobalUnreadBadge("chat-unread", unreadCount);
  }

  function shouldRefreshNotificationBadgeSummary() {
    if (!state.me.loggedIn) {
      return false;
    }
    var userKey = normalizeStudentNumber(state.me.studentNumber);
    var now = Date.now();
    if (userKey !== navBadgeState.notificationsLastUserKey) {
      return true;
    }
    return (now - navBadgeState.notificationsLastFetchedAt) > NAV_BADGE_TTL_MS;
  }

  async function loadNotificationBadgeSummary(force) {
    if (!state.me.loggedIn) {
      navBadgeState.notificationsUnread = 0;
      navBadgeState.notificationsLastUserKey = "";
      navBadgeState.notificationsLastFetchedAt = 0;
      updateChatNavBadges();
      return;
    }
    if (navBadgeState.notificationsPending) {
      return;
    }

    var userKey = normalizeStudentNumber(state.me.studentNumber);
    if (!force && !shouldRefreshNotificationBadgeSummary()) {
      updateChatNavBadges();
      return;
    }

    navBadgeState.notificationsPending = true;
    try {
      var response = await fetch("/api/notifications_api.php?action=summary", {
        credentials: "same-origin",
        headers: {
          Accept: "application/json"
        }
      }).then(function (res) {
        return res.json().catch(function () {
          return {
            success: false,
            error: "پاسخ نامعتبر از سرور دریافت شد."
          };
        }).then(function (payload) {
          payload.httpStatus = res.status;
          return payload;
        });
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        return;
      }
      if (!response || response.success !== true) {
        return;
      }
      navBadgeState.notificationsUnread = Math.max(0, Math.floor(toNumber(response.summary && response.summary.unreadCount, 0)));
      navBadgeState.notificationsLastUserKey = userKey;
      navBadgeState.notificationsLastFetchedAt = Date.now();
      updateChatNavBadges();
      emitGlobalUnreadBadge("notifications", navBadgeState.notificationsUnread);
    } catch (_error) {
      // Keep the last known notification count on transient failures.
    } finally {
      navBadgeState.notificationsPending = false;
    }
  }

  function normalizeSpace(value) {
    return toText(value).replace(/\s+/g, " ").trim();
  }

  function normalizeDigits(value) {
    return toText(value).replace(/[\u06F0-\u06F9\u0660-\u0669]/g, function (char) {
      var code = char.charCodeAt(0);
      if (code >= 0x06F0 && code <= 0x06F9) {
        return String(code - 0x06F0);
      }
      if (code >= 0x0660 && code <= 0x0669) {
        return String(code - 0x0660);
      }
      return char;
    });
  }

  function normalizeStudentNumber(value) {
    var normalized = normalizeSpace(normalizeDigits(value));
    if (!normalized) return "";
    var digitsOnly = normalized.replace(/\D+/g, "");
    return digitsOnly || normalized;
  }

  function reactionStorageKey(kind) {
    var safeKind = normalizeSpace(kind || "recent") || "recent";
    var safeUser = normalizeStudentNumber(state && state.me && state.me.studentNumber) || "guest";
    return "dent1402-chat-reaction-" + safeKind + "-" + safeUser;
  }

  function saveReactionPreferences() {
    try {
      if (!window.localStorage) return;
      var recentPayload = JSON.stringify(state.recentReactions.slice(0, REACTION_RECENTS_LIMIT));
      window.localStorage.setItem(reactionStorageKey("recent"), recentPayload);

      var usagePayload = Array.from(state.reactionUsage.entries())
        .filter(function (pair) {
          return isLikelyEmoji(pair[0]) && toNumber(pair[1], 0) > 0;
        })
        .sort(function (left, right) {
          return toNumber(right[1], 0) - toNumber(left[1], 0);
        })
        .slice(0, REACTION_USAGE_LIMIT);
      window.localStorage.setItem(reactionStorageKey("usage"), JSON.stringify(usagePayload));
    } catch (error) {
      // Ignore storage failures and keep runtime-only behavior.
    }
  }

  function loadReactionPreferences() {
    state.recentReactions = [];
    state.reactionUsage = new Map();

    try {
      if (!window.localStorage) return;

      var recentRaw = window.localStorage.getItem(reactionStorageKey("recent"));
      if (recentRaw) {
        var parsedRecent = JSON.parse(recentRaw);
        if (Array.isArray(parsedRecent)) {
          state.recentReactions = parsedRecent
            .map(function (item) { return normalizeSpace(item); })
            .filter(function (emoji) { return isLikelyEmoji(emoji); })
            .slice(0, REACTION_RECENTS_LIMIT);
        }
      }

      var usageRaw = window.localStorage.getItem(reactionStorageKey("usage"));
      if (usageRaw) {
        var parsedUsage = JSON.parse(usageRaw);
        if (Array.isArray(parsedUsage)) {
          parsedUsage.forEach(function (entry) {
            if (!Array.isArray(entry) || entry.length < 2) return;
            var emoji = normalizeSpace(entry[0]);
            var score = Math.max(0, Math.floor(toNumber(entry[1], 0)));
            if (!isLikelyEmoji(emoji) || score <= 0) return;
            state.reactionUsage.set(emoji, score);
          });
        }
      }
    } catch (error) {
      state.recentReactions = [];
      state.reactionUsage = new Map();
    }
  }

  function isLikelyEmoji(value) {
    var clean = normalizeSpace(value);
    if (!clean || clean.length > 18) return false;
    return /[\p{Extended_Pictographic}\u2600-\u27BF]/u.test(clean);
  }

  function reactionKeywords(emoji) {
    var key = normalizeSpace(emoji);
    var list = REACTION_SEARCH_ALIASES[key];
    return Array.isArray(list) ? list : [];
  }

  function reactionMatchesQuery(emoji, query) {
    var normalizedEmoji = normalizeSpace(emoji);
    var normalizedQuery = normalizeSpace(query).toLowerCase();
    if (!normalizedQuery) return true;
    if (normalizedEmoji.indexOf(query) !== -1 || normalizedEmoji.indexOf(normalizedQuery) !== -1) {
      return true;
    }
    return reactionKeywords(normalizedEmoji).some(function (keyword) {
      return normalizeSpace(keyword).toLowerCase().indexOf(normalizedQuery) !== -1;
    });
  }

  function avatarLabel(value) {
    var clean = normalizeSpace(value);
    if (!clean) return "?";
    var parts = clean.split(" ").filter(Boolean);
    if (parts.length === 1) {
      return parts[0].slice(0, 1).toUpperCase();
    }
    return (parts[0].slice(0, 1) + parts[1].slice(0, 1)).toUpperCase();
  }

  function snippet(value, maxLen) {
    var clean = normalizeSpace(value);
    if (clean.length <= maxLen) return clean;
    return clean.slice(0, maxLen - 1) + "…";
  }

  function clamp(number, min, max) {
    return Math.max(min, Math.min(max, number));
  }

  function toNumber(value, fallback) {
    var num = Number(value);
    return Number.isFinite(num) ? num : fallback;
  }

  function formatTime(ts) {
    var n = toNumber(ts, 0);
    if (!n) return "";
    try {
      var date = new Date(n * 1000);
      var now = new Date();
      var sameDay = date.getFullYear() === now.getFullYear()
        && date.getMonth() === now.getMonth()
        && date.getDate() === now.getDate();
      if (sameDay) {
        return date.toLocaleTimeString("fa-IR-u-ca-persian", { hour: "2-digit", minute: "2-digit", hour12: false });
      }

      var yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
      var isYesterday = date.getFullYear() === yesterday.getFullYear()
        && date.getMonth() === yesterday.getMonth()
        && date.getDate() === yesterday.getDate();
      if (isYesterday) {
        return "دیروز";
      }

      if (date.getFullYear() === now.getFullYear()) {
        return date.toLocaleDateString("fa-IR-u-ca-persian", { month: "short", day: "numeric" });
      }

      return date.toLocaleDateString("fa-IR-u-ca-persian", { year: "numeric", month: "short", day: "numeric" });
    } catch (error) {
      return "";
    }
  }

  function formatClock(ts) {
    var n = toNumber(ts, 0);
    if (!n) return "";
    try {
      return new Date(n * 1000).toLocaleTimeString("fa-IR-u-ca-persian", { hour: "2-digit", minute: "2-digit", hour12: false });
    } catch (error) {
      return "";
    }
  }

  function formatDate(ts) {
    var n = toNumber(ts, 0);
    if (!n) return "";
    try {
      return new Date(n * 1000).toLocaleDateString("fa-IR-u-ca-persian", { month: "short", day: "numeric" });
    } catch (error) {
      return "";
    }
  }

  function formatDateTime(ts) {
    var date = formatDate(ts);
    var clock = formatClock(ts);
    return [date, clock].filter(Boolean).join(" ");
  }

  function formatLastSeenLabel(ts) {
    var n = Math.max(0, Math.floor(toNumber(ts, 0)));
    if (!n) return "";
    var now = Math.floor(Date.now() / 1000);
    var delta = Math.max(0, now - n);
    if (delta < 60) return "لحظاتی پیش";
    if (delta < 3600) return Math.max(1, Math.floor(delta / 60)).toLocaleString("fa-IR") + " دقیقه پیش";
    if (delta < 21600) return formatClock(n);
    return formatDateTime(n);
  }

  function parseInternalRouteHref(value) {
    var href = normalizeSpace(value);
    if (!href || href.charAt(0) !== "/" || href.indexOf("//") === 0) {
      return "";
    }
    try {
      var resolved = new URL(href, window.location.origin);
      if (resolved.origin !== window.location.origin) {
        return "";
      }
      var path = resolved.pathname || "/";
      var allowed = path === "/notes" || path.indexOf("/notes/") === 0
        || path === "/exams" || path.indexOf("/exams/") === 0
        || path === "/forms" || path.indexOf("/forms/") === 0;
      if (!allowed) {
        return "";
      }
      return resolved.pathname + resolved.search + resolved.hash;
    } catch (_error) {
      return "";
    }
  }

  function routeCardSectionFromHref(href) {
    var cleanHref = parseInternalRouteHref(href);
    if (!cleanHref) return "";
    try {
      var resolved = new URL(cleanHref, window.location.origin);
      var path = resolved.pathname || "/";
      if (path === "/notes" || path.indexOf("/notes/") === 0) return "notes";
      if (path === "/exams" || path.indexOf("/exams/") === 0) return "exams";
      if (path === "/forms" || path.indexOf("/forms/") === 0) return "forms";
    } catch (_error) {}
    return "";
  }

  function routeCardSectionLabel(section) {
    switch (normalizeSpace(section)) {
      case "notes":
        return "جزوه";
      case "exams":
        return "آزمون";
      case "forms":
        return "فرم";
      default:
        return "لینک";
    }
  }

  function routeCardDefaultTitle(section) {
    switch (normalizeSpace(section)) {
      case "notes":
        return "جزوه و منبع مرتبط";
      case "exams":
        return "آزمون مرتبط";
      case "forms":
        return "فرم مرتبط";
      default:
        return "لینک داخلی";
    }
  }

  function taskReminderCategoryLabel(category) {
    switch (normalizeSpace(category)) {
      case "exam":
        return "آزمون";
      case "form":
        return "فرم";
      default:
        return "یادآور";
    }
  }

  function scopedInternalHref(href) {
    var cleanHref = parseInternalRouteHref(href);
    return cleanHref ? scopedChatPath(cleanHref) : "";
  }

  function normalizeMessageMeta(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var type = normalizeSpace(source.type || source.cardType || "");
    if (type === "route-link") {
      var routeSource = asObject(source.routeLink) || source;
      var routeHref = parseInternalRouteHref(routeSource.href || routeSource.ctaHref || "");
      var section = normalizeSpace(routeSource.section || routeCardSectionFromHref(routeHref));
      if (!routeHref || !section) return null;
      return {
        type: "route-link",
        routeLink: {
          section: section,
          href: routeHref,
          title: normalizeSpace(routeSource.title) || routeCardDefaultTitle(section),
          description: normalizeSpace(routeSource.description || routeSource.subtitle || ""),
          ctaLabel: normalizeSpace(routeSource.ctaLabel || "") || "باز کردن",
          badge: normalizeSpace(routeSource.badge || "") || routeCardSectionLabel(section)
        }
      };
    }
    if (type === "task-reminder") {
      var taskSource = asObject(source.taskReminder) || source;
      var category = normalizeSpace(taskSource.category || "deadline");
      if (category !== "exam" && category !== "form" && category !== "deadline") {
        category = "deadline";
      }
      var title = normalizeSpace(taskSource.title);
      if (!title) return null;
      var dueAt = taskSource.dueAt != null ? Math.max(0, Math.floor(toNumber(taskSource.dueAt, 0))) : 0;
      if (!dueAt) {
        var dueAtIso = normalizeSpace(taskSource.dueAtIso || "");
        if (dueAtIso) {
          var parsedTs = Date.parse(dueAtIso);
          if (Number.isFinite(parsedTs) && parsedTs > 0) {
            dueAt = Math.floor(parsedTs / 1000);
          }
        }
      }
      var tone = normalizeSpace(taskSource.tone || "normal");
      if (tone !== "important" && tone !== "urgent") {
        tone = "normal";
      }
      var ctaHref = parseInternalRouteHref(taskSource.ctaHref || taskSource.href || "");
      return {
        type: "task-reminder",
        taskReminder: {
          category: category,
          categoryLabel: normalizeSpace(taskSource.categoryLabel || "") || taskReminderCategoryLabel(category),
          title: title,
          details: normalizeSpace(taskSource.details || taskSource.description || ""),
          dueAt: dueAt > 0 ? dueAt : null,
          dueAtIso: normalizeSpace(taskSource.dueAtIso || ""),
          ctaHref: ctaHref,
          ctaLabel: ctaHref ? (normalizeSpace(taskSource.ctaLabel || "") || "باز کردن") : "",
          tone: tone
        }
      };
    }
    return null;
  }

  function normalizePresenceState(raw, conversationId) {
    var source = asObject(raw) || {};
    var currentConversationId = normalizeSpace(conversationId || "");
    var typingConversationId = normalizeSpace(source.typingConversationId || "");
    var stateObj = {
      studentNumber: normalizeStudentNumber(source.studentNumber || source.username),
      isOnline: !!source.isOnline,
      isTyping: !!source.isTyping,
      lastSeenAt: source.lastSeenAt != null ? Math.floor(toNumber(source.lastSeenAt, 0)) : null,
      lastActiveAt: source.lastActiveAt != null ? Math.floor(toNumber(source.lastActiveAt, 0)) : null,
      lastHeartbeatAt: source.lastHeartbeatAt != null ? Math.floor(toNumber(source.lastHeartbeatAt, 0)) : null,
      activeConversationId: normalizeSpace(source.activeConversationId || ""),
      typingConversationId: typingConversationId,
      typingExpiresAt: source.typingExpiresAt != null ? Math.floor(toNumber(source.typingExpiresAt, 0)) : null
    };
    if (currentConversationId && typingConversationId && typingConversationId === currentConversationId && stateObj.typingExpiresAt) {
      stateObj.isTyping = true;
    }
    return stateObj;
  }

  function normalizeConversationPresence(raw, conversationId) {
    var source = asObject(raw) || {};
    var currentConversationId = normalizeSpace(conversationId || source.conversationId || "");
    return {
      conversationId: currentConversationId,
      onlineCount: Math.max(0, Math.floor(toNumber(source.onlineCount, 0))),
      peer: source.peer ? normalizePresenceState(source.peer, currentConversationId) : null,
      typingUsers: (Array.isArray(source.typingUsers) ? source.typingUsers : []).map(function (user) {
        var normalized = normalizeUser(user);
        if (!normalized) return null;
        normalized.presence = normalizePresenceState(user && user.presence ? user.presence : {}, currentConversationId);
        return normalized;
      }).filter(Boolean)
    };
  }

  function isPrivateLikeConversationType(type) {
    var normalized = normalizeSpace(type || "").toLowerCase();
    return normalized === "direct" || normalized === "saved";
  }

  function userPresenceText(user, conversationId) {
    var presence = user && user.presence ? normalizePresenceState(user.presence, conversationId) : null;
    if (!presence) return "";
    if (presence.isTyping) return "در حال نوشتن…";
    if (presence.isOnline) return "آنلاین";
    if (presence.lastSeenAt) return "آخرین بازدید " + formatLastSeenLabel(presence.lastSeenAt);
    return "";
  }

  function conversationPresenceText(conversation) {
    if (conversation && conversation.type === "saved") return "";
    var presence = conversation && conversation.presence ? conversation.presence : null;
    if (!presence) return "";
    var typingUsers = Array.isArray(presence.typingUsers) ? presence.typingUsers : [];
    if (typingUsers.length === 1) {
      var typingName = normalizeSpace(typingUsers[0].name) || "کاربر";
      return typingName + " در حال نوشتن…";
    }
    if (typingUsers.length > 1) {
      return typingUsers.length.toLocaleString("fa-IR") + " نفر در حال نوشتن…";
    }
    if (conversation && conversation.type === "direct") {
      if (presence.peer) {
        if (presence.peer.isOnline) return "آنلاین";
        if (presence.peer.lastSeenAt) return "آخرین بازدید " + formatLastSeenLabel(presence.peer.lastSeenAt);
      }
      return "";
    }
    if (presence.onlineCount > 0) {
      return presence.onlineCount.toLocaleString("fa-IR") + " نفر آنلاین";
    }
    return "";
  }

  function setPresenceBadge(node, conversation) {
    if (!node || !node.classList) return;
    var mode = "";
    var presence = conversation && conversation.presence ? conversation.presence : null;
    if (conversation && conversation.type === "direct" && presence && presence.peer) {
      if (presence.peer.isTyping) {
        mode = "typing";
      } else if (presence.peer.isOnline) {
        mode = "online";
      }
    }
    if (mode) {
      node.dataset.presence = mode;
    } else if (node.dataset) {
      delete node.dataset.presence;
    }
    node.classList.toggle("is-live-presence", !!mode);
  }

  function dayKeyFromTimestamp(ts) {
    var n = toNumber(ts, 0);
    if (!n) return "";
    var date = new Date(n * 1000);
    if (Number.isNaN(date.getTime())) return "";
    return [
      String(date.getFullYear()),
      String(date.getMonth() + 1).padStart(2, "0"),
      String(date.getDate()).padStart(2, "0")
    ].join("-");
  }

  function formatFileSize(bytes) {
    var size = Math.max(0, Math.floor(toNumber(bytes, 0)));
    if (!size) return "0 B";
    if (size < 1024) return size + " B";
    if (size < (1024 * 1024)) return (size / 1024).toFixed(1) + " KB";
    if (size < (1024 * 1024 * 1024)) return (size / (1024 * 1024)).toFixed(1) + " MB";
    return (size / (1024 * 1024 * 1024)).toFixed(1) + " GB";
  }

  function formatDuration(seconds) {
    var total = Math.max(0, Math.floor(toNumber(seconds, 0)));
    var mins = Math.floor(total / 60);
    var secs = total % 60;
    return String(mins).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
  }

  function voiceSpeedLabel(rate) {
    var normalized = Math.max(1, toNumber(rate, 1));
    return (Math.round(normalized * 10) / 10).toLocaleString("fa-IR") + "x";
  }

  function stableHashSeed(value) {
    var text = toText(value);
    var hash = 0;
    for (var i = 0; i < text.length; i += 1) {
      hash = ((hash << 5) - hash) + text.charCodeAt(i);
      hash |= 0;
    }
    return Math.abs(hash) || 1;
  }

  function seededWaveformSamples(seed, count) {
    var total = Math.max(6, Math.floor(toNumber(count, 0)));
    var stateSeed = stableHashSeed(seed);
    var samples = [];
    for (var i = 0; i < total; i += 1) {
      stateSeed = (stateSeed * 1664525 + 1013904223) >>> 0;
      var noise = (stateSeed / 4294967295);
      var swing = 0.28 + (Math.sin((i + 1) * 0.62 + noise * 2.8) * 0.22);
      samples.push(clamp(0.22 + noise * 0.56 + swing, 0.2, 0.96));
    }
    return samples;
  }

  function renderWaveBarsMarkup(samples, className) {
    var bars = Array.isArray(samples) ? samples : [];
    var barClass = className || "msg-voice-note__bar";
    return bars.map(function (sample) {
      var height = Math.round(clamp(toNumber(sample, 0.4), 0.14, 1) * 1000) / 10;
      return '<span class="' + escapeHtml(barClass) + '" style="--voice-bar-height:' + height + '%"></span>';
    }).join("");
  }

  function normalizeAttachmentCategory(value) {
    var category = normalizeSpace(value).toLowerCase();
    if (
      category === "image" ||
      category === "video" ||
      category === "audio" ||
      category === "voice" ||
      category === "document" ||
      category === "pdf" ||
      category === "office" ||
      category === "archive"
    ) {
      return category;
    }
    return "file";
  }

  function attachmentCategoryLabel(category) {
    switch (normalizeAttachmentCategory(category)) {
      case "image":
        return "تصویر";
      case "video":
        return "ویدیو";
      case "audio":
        return "فایل صوتی";
      case "voice":
        return "پیام صوتی";
      case "document":
        return "سند";
      case "pdf":
        return "PDF";
      case "office":
        return "Office";
      case "archive":
        return "آرشیو";
      default:
        return "فایل";
    }
  }

  function messagePreviewText(message) {
    if (!message) return "";
    var text = meaningfulMessageText(message);
    if (message.poll && message.poll.question) {
      return "نظرسنجی: " + message.poll.question;
    }
    if (text) return text;
    if (message.meta) {
      if (message.meta.type === "route-link" && message.meta.routeLink) {
        return normalizeSpace(message.meta.routeLink.title || message.meta.routeLink.badge || "");
      }
      if (message.meta.type === "task-reminder" && message.meta.taskReminder) {
        return "یادآور: " + normalizeSpace(message.meta.taskReminder.title || "");
      }
    }
    if (Array.isArray(message.attachments) && message.attachments.length) {
      if (message.attachments.length === 1) {
        return attachmentCategoryLabel(message.attachments[0].category || "file");
      }
      return message.attachments.length.toLocaleString("fa-IR") + " فایل";
    }
    if (message.forwardedFrom) {
      return normalizeSpace(message.forwardedFrom.previewText || message.forwardedFrom.attachmentSummary || "");
    }
    return "";
  }

  function isGeneratedMessagePlaceholder(message, text) {
    var normalizedText = normalizeSpace(text);
    if (!normalizedText) return false;
    var hasAttachments = !!(message && Array.isArray(message.attachments) && message.attachments.length);
    var kind = normalizeSpace(message && message.kind || "text");
    if (hasAttachments && normalizedText.toLowerCase() === "attachment") {
      return true;
    }
    if ((kind === "poll" || !!(message && message.poll)) && normalizedText.toLowerCase() === "poll") {
      return true;
    }
    if (message && message.meta && (normalizedText === "RouteCard" || normalizedText === "TaskReminder")) {
      return true;
    }
    return false;
  }

  function meaningfulMessageText(message) {
    if (!message) return "";
    var text = toText(message.text);
    if (!text) return "";
    return isGeneratedMessagePlaceholder(message, text) ? "" : normalizeSpace(text);
  }

  var BLANK_AVATAR_DATA_URL = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn0K1sAAAAASUVORK5CYII=";

  function normalizeAvatarUrl(value) {
    var clean = toText(value).trim();
    if (!clean) return "";
    if (clean === BLANK_AVATAR_DATA_URL) return "";
    if (clean.indexOf("data:image/") === 0) return clean;
    if (clean.charAt(0) === "/") return clean;
    if (/^https?:\/\//i.test(clean)) return clean;
    return "";
  }

  function renderAvatar(container, imageNode, fallbackNode, avatarUrl, labelText) {
    if (!container || !imageNode || !fallbackNode) return;
    var safeUrl = normalizeAvatarUrl(avatarUrl);
    fallbackNode.textContent = avatarLabel(labelText);
    if (!safeUrl) {
      container.dataset.hasAvatar = "0";
      imageNode.hidden = true;
      imageNode.removeAttribute("src");
      imageNode.alt = "";
      return;
    }

    container.dataset.hasAvatar = "1";
    imageNode.hidden = false;
    imageNode.alt = labelText ? "تصویر " + labelText : "تصویر کاربر";
    imageNode.onerror = function () {
      container.dataset.hasAvatar = "0";
      imageNode.hidden = true;
      imageNode.removeAttribute("src");
    };
    imageNode.src = safeUrl;
  }

  function setHidden(node, hidden) {
    if (!node) return;
    node.hidden = !!hidden;
  }

  function setThreadUpdating(flag) {
    if (flag) {
      threadUpdatingCount = Math.max(0, threadUpdatingCount) + 1;
    } else {
      threadUpdatingCount = Math.max(0, threadUpdatingCount - 1);
    }
    if (threadUpdatingCount > 0) {
      if (conversationTitle) {
        conversationTitle.textContent = "درحال بروزرسانی";
        conversationTitle.dataset.updating = "1";
      }
      if (threadTitle) {
        threadTitle.textContent = "درحال بروزرسانی";
        threadTitle.dataset.updating = "1";
      }
    } else {
      if (conversationTitle) {
        conversationTitle.textContent = "گفتگوها";
        conversationTitle.dataset.updating = "";
      }
      if (threadTitle) {
        threadTitle.dataset.updating = "";
      }
      updateThreadHead();
    }
  }

  function callIf(fn) {
    if (typeof fn === "function") {
      return fn;
    }
    return null;
  }

  var bootBox = $("boot-box");
  var bootText = $("boot-text");
  var loginBox = $("login-box");
  var chatBox = $("chat-box");
  var msgBox = $("msg");

  var chatApp = $("chat-app");
  var conversationPane = $("conversation-pane");
  var threadPane = $("thread-pane");
  var threadPlaceholder = $("thread-placeholder");
  var threadShell = $("thread-shell");
  var chatDropOverlay = $("chat-drop-overlay");
  var placeholderActiveCount = $("placeholder-active-count");
  var placeholderUnreadCount = $("placeholder-unread-count");
  var placeholderClassLabel = $("placeholder-class-label");
  var placeholderActionButtons = Array.from(document.querySelectorAll("[data-placeholder-action]"));

  var connectionBadge = $("connection-badge");
  var accountBtn = $("account-btn");
  var accountBtnAvatarImage = $("account-btn-avatar-image");
  var accountBtnAvatarFallback = $("account-btn-avatar-fallback");
  var logoutBtn = $("logout-btn");
  var refreshBtn = $("refresh-btn");

  var conversationTitle = $("conversation-title");
  var conversationMeta = $("conversation-meta");
  var conversationSearch = $("conversation-search");
  var conversationSearchWrap = $("conversation-search-wrap");
  var conversationSearchBtn = $("conversation-search-btn");
  var conversationFilterTabs = $("conversation-filter-tabs");
  var conversationManageBtn = $("conversation-manage-btn");
  var conversationManageBar = $("conversation-manage-bar");
  var conversationSelectionCount = $("conversation-selection-count");
  var conversationSelectionToggleAll = $("conversation-selection-toggle-all");
  var conversationSelectionClear = $("conversation-selection-clear");
  var conversationBatchRead = $("conversation-batch-read");
  var conversationBatchUnread = $("conversation-batch-unread");
  var conversationBatchPin = $("conversation-batch-pin");
  var conversationBatchUnpin = $("conversation-batch-unpin");
  var conversationBatchArchive = $("conversation-batch-archive");
  var conversationBatchUnarchive = $("conversation-batch-unarchive");
  var conversationBatchMute = $("conversation-batch-mute");
  var conversationBatchUnmute = $("conversation-batch-unmute");
  var conversationBatchDelete = $("conversation-batch-delete");
  var conversationList = $("conversation-list");
  var conversationEmpty = $("conversation-empty");
  var conversationQuickActionButtons = Array.from(document.querySelectorAll("[data-chat-quick-action]"));
  var newPollLink = null;
  var mobileOpenListBtn = $("mobile-open-list");
  var mobileCloseListBtn = $("mobile-close-list");
  var mobileNewChatFab = $("mobile-new-chat-fab");
  var chatMobileNav = $("chat-mobile-nav");
  var chatNavList = $("chat-nav-list");
  var chatNavListBadge = $("chat-nav-list-badge");
  var chatNavCompose = $("chat-nav-compose");
  var chatNavGroup = $("chat-nav-group");
  var chatNavPolls = null;
  var chatNavHome = $("chat-nav-home");
  var chatNavExams = $("chat-nav-exams");
  var chatNavSettings = $("chat-nav-settings");
  var chatNavSettingsBadge = $("chat-nav-settings-badge");

  var threadInfoTrigger = $("thread-info-trigger");
  var threadAvatar = $("thread-avatar");
  var threadAvatarImage = $("thread-avatar-image");
  var threadAvatarFallback = $("thread-avatar-fallback");
  var threadTitle = $("thread-title");
  var threadSubtitle = $("thread-subtitle");
  var threadSearchToggle = $("thread-search-toggle");
  var threadSearchPanel = $("thread-search-panel");
  var threadSearchInput = $("thread-search-input");
  var threadSearchClose = $("thread-search-close");
  var threadSearchPrev = $("thread-search-prev");
  var threadSearchNext = $("thread-search-next");
  var threadSearchCount = $("thread-search-count");
  var threadSearchPreview = $("thread-search-preview");
  var threadDaySelect = $("thread-day-select");
  var threadJumpDayBtn = $("thread-jump-day-btn");
  var threadJumpUnreadBtn = $("thread-jump-unread-btn");
  var messageManageBar = $("message-manage-bar");
  var messageSelectionCount = $("message-selection-count");
  var messageSelectionToggleAll = $("message-selection-toggle-all");
  var messageSelectionClear = $("message-selection-clear");
  var messageBatchCopy = $("message-batch-copy");
  var messageBatchForward = $("message-batch-forward");
  var messageBatchPin = $("message-batch-pin");
  var messageBatchUnpin = $("message-batch-unpin");
  var messageBatchDelete = $("message-batch-delete");
  var threadUpdatingCount = 0;

  var muteBadge = $("mute-badge");
  var pinnedWrap = $("pinned-wrap");
  var pinnedText = $("pinned-text");
  var pinnedActionLabel = $("pinned-action-label");
  var streamStateEl = $("stream-state");
  var messagesEl = $("messages");
  var chatTextEl = $("chat-text");
  var sendBtn = $("send-btn");
  var attachBtn = $("attach-btn");
  var emojiBtn = $("emoji-btn");
  var emojiPanel = $("composer-emoji-panel");
  var emojiTabs = $("composer-emoji-tabs");
  var emojiGrid = $("composer-emoji-grid");
  var voiceBtn = $("voice-btn");
  var pollBtn = $("poll-btn");
  var mentionSuggestions = $("mention-suggestions");
  var attachmentInput = $("attachment-input");
  var composerUploadSheet = $("composer-upload-sheet");
  var composerUploads = $("composer-uploads");
  var composerVoice = $("composer-voice");
  var composerVoiceTimer = $("composer-voice-timer");
  var composerVoiceWave = $("composer-voice-wave");
  var composerVoiceHint = $("composer-voice-hint");
  var composerVoiceLockChip = $("composer-voice-lock-chip");
  var voiceCancelBtn = $("voice-cancel-btn");
  var voiceStopBtn = $("voice-stop-btn");
  var voiceSendBtn = $("voice-send-btn");
  var composerStatus = $("composer-status");
  var replyBar = $("reply-bar");
  var replyToName = $("reply-to-name");
  var replyToSnippet = $("reply-to-snippet");
  var replyCancel = $("reply-cancel");

  var contextBackdrop = $("chat-context-backdrop");
  var contextMenu = $("chat-context-menu");
  var reactionBar = $("chat-reaction-bar");
  var contextActions = $("chat-context-actions");
  var listContextBackdrop = $("chat-list-context-backdrop");
  var listContextMenu = $("chat-list-context-menu");
  var listContextTitle = $("chat-list-context-title");
  var listContextSubtitle = $("chat-list-context-subtitle");
  var listContextActions = $("chat-list-context-actions");

  var infoSheet = $("chat-info-sheet");
  var infoSheetBackdrop = $("chat-sheet-backdrop");
  var infoSheetClose = $("chat-sheet-close");
  var peerSheet = $("chat-peer-sheet");
  var peerSheetClose = $("chat-peer-sheet-close");
  var peerAvatar = $("chat-peer-avatar");
  var peerAvatarImg = $("chat-peer-avatar-image");
  var peerAvatarFallback = $("chat-peer-avatar-fallback");
  var peerName = $("chat-peer-name");
  var peerStatus = $("chat-peer-status");
  var peerAbout = $("chat-peer-about");
  var peerQuickActions = $("chat-peer-quick-actions");
  var peerInfoBlock = $("chat-peer-info-block");
  var peerInfoRows = $("chat-peer-info-rows");
  var infoProfile = $("chat-info-profile");
  var infoTitle = $("chat-info-title");
  var infoStatus = $("chat-info-status");
  var infoAbout = $("chat-info-about");
  var infoIdentityRows = $("chat-info-identity-rows");
  var infoContentTabs = $("chat-info-content-tabs");
  var infoContentTable = $("chat-info-content-table");
  var infoMembers = $("chat-info-members");
  var infoMembersBlock = $("chat-info-members-block");
  var infoMembersLabel = $("chat-info-members-label");
  var infoActionsBlock = $("chat-info-actions-block");
  var infoAvatar = $("chat-info-avatar");
  var infoAvatarImage = $("chat-info-avatar-image");
  var infoAvatarFallback = $("chat-info-avatar-fallback");
  var infoCopyLink = $("chat-info-copy-link");
  var infoNotificationBtn = $("chat-info-notification-btn");
  var infoEditProfileBtn = $("chat-info-edit-profile-btn");
  var infoGroupTypeBtn = $("chat-info-group-type-btn");
  var infoReactionSettingsBtn = $("chat-info-reaction-settings-btn");
  var infoAddMembersBtn = $("chat-info-add-members-btn");
  var infoProfileLink = $("chat-info-profile-link");
  var infoSecurityLink = $("chat-info-security-link");
  var infoAccountLink = $("chat-info-account-link");
  var archiveBtn = $("archive-btn");
  var unarchiveBtn = $("unarchive-btn");
  var clearHistoryBtn = $("clear-history-btn");
  var leaveBtn = $("leave-btn");
  var deleteConversationBtn = $("delete-conversation-btn");
  var adminTools = $("chat-admin-tools");
  var muteBtn = $("mute-btn");
  var unmuteBtn = $("unmute-btn");
  var unpinBtn = $("unpin-btn");

  var modalBackdrop = $("chat-modal-backdrop");
  var dmModal = $("dm-modal");
  var dmModalClose = $("dm-modal-close");
  var dmSearch = $("dm-search");
  var dmList = $("dm-list");
  var groupModal = $("group-modal");
  var groupModalClose = $("group-modal-close");
  var groupModalSubtitle = $("group-modal-subtitle");
  var groupStepMembers = $("group-step-members");
  var groupStepDetails = $("group-step-details");
  var groupStepBack = $("group-step-back");
  var groupStepNext = $("group-step-next");
  var groupTitleInput = $("group-title");
  var groupAboutInput = $("group-about");
  var groupKindGroupInput = $("group-kind-group");
  var groupKindChannelInput = $("group-kind-channel");
  var groupSearch = $("group-search");
  var groupSelectionMeta = $("group-selection-meta");
  var groupSelectedMembers = $("group-selected-members");
  var groupMembers = $("group-members");
  var groupCreateBtn = $("group-create-btn");
  var forwardModal = $("forward-modal");
  var forwardModalClose = $("forward-modal-close");
  var forwardSourcePreview = $("forward-source-preview");
  var forwardIncludeSenderInput = $("forward-include-sender");
  var forwardSearch = $("forward-search");
  var forwardList = $("forward-list");
  var reactionModal = $("reaction-modal");
  var reactionModalClose = $("reaction-modal-close");
  var reactionSearch = $("reaction-search");
  var reactionNativeWrap = $("reaction-native-wrap");
  var reactionNativeBtn = $("reaction-native-btn");
  var reactionTabs = $("reaction-tabs");
  var reactionGrid = $("reaction-grid");
  var reactionDetailsModal = $("reaction-details-modal");
  var reactionDetailsModalClose = $("reaction-details-modal-close");
  var reactionDetailsModalTitle = $("reaction-details-modal-title");
  var reactionDetailsList = $("reaction-details-list");
  var editModal = $("edit-modal");
  var editModalClose = $("edit-modal-close");
  var editCancelBtn = $("edit-cancel");
  var editSaveBtn = $("edit-save");
  var editTextInput = $("edit-text");
  var pollModal = $("poll-modal");
  var pollModalClose = $("poll-modal-close");
  var pollQuestionInput = $("poll-question");
  var pollOptionsList = $("poll-options-list");
  var pollAddOptionBtn = $("poll-add-option");
  var pollMultipleChoiceInput = $("poll-multiple-choice");
  var pollMaxChoicesField = $("poll-max-choices-field");
  var pollMaxChoicesInput = $("poll-max-choices");
  var pollAnonymousInput = $("poll-anonymous");
  var pollAllowChangeInput = $("poll-allow-change");
  var pollCancelBtn = $("poll-cancel");
  var pollCreateBtn = $("poll-create");
  var conversationOptionsModal = $("conversation-options-modal");
  var conversationOptionsClose = $("conversation-options-close");
  var conversationOptionsTitle = $("conversation-options-title");
  var conversationOptionsBody = $("conversation-options-body");
  var confirmModal = $("confirm-modal");
  var confirmModalClose = $("confirm-modal-close");
  var confirmModalTitle = $("confirm-modal-title");
  var confirmModalText = $("confirm-modal-text");
  var confirmCancelBtn = $("confirm-cancel");
  var confirmAcceptBtn = $("confirm-accept");
  var receiptsModal = $("receipts-modal");
  var receiptsModalClose = $("receipts-modal-close");
  var receiptsList = $("receipts-list");
  var mediaViewer = $("chat-media-viewer");
  var mediaViewerClose = $("chat-media-viewer-close");
  var mediaViewerStage = $("chat-media-viewer-stage");
  var mediaViewerCaption = $("chat-media-viewer-caption");
  var mediaViewerMeta = $("chat-media-viewer-meta");
  var mediaViewerSender = $("chat-media-viewer-sender");
  var mediaViewerTime = $("chat-media-viewer-time");
  var mediaViewerRotate = $("chat-media-viewer-rotate");
  var mediaViewerForward = $("chat-media-viewer-forward");
  var mediaViewerDownload = $("chat-media-viewer-download");
  var mediaViewerDelete = $("chat-media-viewer-delete");
  var mediaViewerPrev = null;
  var mediaViewerNext = null;
  var mediaViewerCounter = null;
  var mediaViewerCloseTimer = null;
  var MEDIA_VIEWER_FADE_MS = 160;
  var MEDIA_VIEWER_SETTLE_MS = 180;
  var imageEditor = $("chat-image-editor");
  var imageEditorClose = $("chat-image-editor-close");
  var imageEditorDone = $("chat-image-editor-done");
  var imageEditorRotate = $("chat-image-editor-rotate");
  var imageEditorCrop = $("chat-image-editor-crop");
  var imageEditorDraw = $("chat-image-editor-draw");
  var imageEditorFilter = $("chat-image-editor-filter");
  var imageEditorFilterLabel = $("chat-image-editor-filter-label");
  var imageEditorCanvas = $("chat-image-editor-canvas");
  var imageEditorStage = $("chat-image-editor-stage");
  var imageEditorCropBox = $("chat-image-editor-crop-box");
  var imageEditorDrawOptions = $("chat-image-editor-draw-options");
  var imageEditorState = null;
  var IMAGE_EDITOR_FILTERS = [
    { id: "none", label: "بدون فیلتر", css: "none" },
    { id: "grayscale", label: "سیاه و سفید", css: "grayscale(1)" },
    { id: "sepia", label: "قدیمی", css: "sepia(0.65) saturate(1.2)" },
    { id: "bright", label: "روشن", css: "brightness(1.18) saturate(1.05)" },
    { id: "contrast", label: "کنتراست", css: "contrast(1.25)" },
    { id: "cool", label: "سرد", css: "hue-rotate(-12deg) saturate(1.1)" }
  ];

  var toastEl = $("toast");
  var themeColorMetas = Array.from(document.querySelectorAll('meta[name="theme-color"]'));
  var appleStatusBarMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');

  if (!chatApp || !bootBox || !loginBox || !chatBox) {
    return;
  }

  var state = {
    me: {
      loggedIn: false,
      studentNumber: "",
      name: "",
      role: "student",
      roleLabel: "دانشجو",
      canModerateChat: false,
      isOwner: false,
      isRepresentative: false,
      profile: {
        avatarUrl: "",
        about: ""
      }
    },
    activeConversationId: "",
    conversations: [],
    conversationsById: new Map(),
    conversationListVersion: "",
    conversationFilter: "",
    conversationListCategory: "all",
    messages: new Map(),
    messageOrderCache: [],
    messageOrderDirty: true,
    cacheHydrated: false,
    cacheHydratedAt: 0,
    cacheSaveTimer: null,
    cacheSaveIdleId: 0,
    draftSaveTimer: null,
    lastMessageId: 0,
    oldestMessageId: 0,
    hasMoreBefore: false,
    olderMessagesLoading: false,
    replyTargetId: null,
    unreadDividerMessageId: 0,
    pollingTimer: null,
    pollIntervalMs: 1700,
    pollInFlight: false,
    requestToken: 0,
    toastTimer: null,
    contextOpen: false,
    contextAnchorMessageId: null,
    messageSelectionMode: false,
    selectedMessageIds: new Set(),
    listContextOpen: false,
    listContextConversationId: "",
    listSelectionMode: false,
    selectedConversationIds: new Set(),
    infoSheetOpen: false,
    peerSheetOpen: false,
    infoContentCategory: "media",
    modalOpen: "",
    groupCreateStep: "members",
    pendingDirectStart: false,
    pendingGroupCreate: false,
    pendingForwardMessageId: null,
    pendingForwardMessageIds: [],
    forwardIncludeSenderName: true,
    pendingReactionMessageId: null,
    reactionCategory: "recent",
    recentReactions: [],
    reactionUsage: new Map(),
    reactionDetailsRequestToken: 0,
    pendingEditMessageId: null,
    conversationOptionsMode: "",
    confirmDialog: null,
    connectionIssue: false,
    showArchivedConversations: false,
    conversationListRenderKey: "",
    threadAutoStick: true,
    threadSearchOpen: false,
    threadSearchQuery: "",
    threadSearchResults: [],
    threadSearchIndex: -1,
    threadSearchRequestToken: 0,
    threadSearchDebounceTimer: null,
    threadNavigatorConversationId: "",
    threadNavigatorDays: [],
    threadNavigatorFirstUnreadMessageId: 0,
    autoReadTimer: null,
    autoReadConversationId: "",
    autoReadMessageId: 0,
    initialConversationId: normalizeSpace(new URLSearchParams(window.location.search).get("conversationId")),
    initialMessageId: Math.max(0, Math.floor(toNumber(new URLSearchParams(window.location.search).get("messageId"), 0))),
    groupMemberSelection: new Set(),
    directoryUsers: [],
    directoryLoaded: false,
    pendingAttachments: [],
    voiceRecorder: null,
    voiceGesture: null,
    activeVoiceNoteId: "",
    mediaViewerItems: [],
    mediaViewerIndex: -1,
    mediaViewerZoomed: false,
    mediaViewerPointer: null,
    mediaViewerScale: 1,
    mediaViewerRotation: 0,
    mediaViewerOffsetX: 0,
    mediaViewerOffsetY: 0,
    mediaViewerActivePointers: new Map(),
    mediaViewerPinch: null,
    mediaViewerPanPointer: null,
    transportMode: "polling",
    transportStreamUrl: "",
    transportPresenceUrl: "",
    streamSource: null,
    streamConnected: false,
    streamBoundConversationId: "",
    streamBoundMembers: false,
    streamRetryTimer: null,
    streamSyncTimer: null,
    presenceHeartbeatTimer: null,
    presenceRequestInFlight: false,
    presenceRefreshPending: false,
    typingConversationId: "",
    typingActive: false,
    typingRefreshTimer: null,
    typingStopTimer: null,
    mentionQuery: "",
    mentionSuggestions: [],
    mentionSelectedIndex: -1,
    mentionTokenStart: -1,
    mentionTokenEnd: -1,
    pollDraftSelections: new Map(),
    emojiCategory: EMOJI_CATEGORIES[0].key
  };
  var navBadgeState = {
    notificationsUnread: 0,
    notificationsPending: false,
    notificationsLastUserKey: "",
    notificationsLastFetchedAt: 0
  };
  var NAV_BADGE_TTL_MS = 45000;
  var nativeEmojiPicker = null;

  function safeAuthApi() {
    return authApi();
  }

  async function parseApiResponse(response) {
    var status = response.status;
    var text = "";
    var payload = null;

    try {
      text = await response.text();
    } catch (error) {
      text = "";
    }

    if (text) {
      try {
        payload = JSON.parse(text);
      } catch (error) {
        payload = null;
      }
    }

    if (!asObject(payload)) {
      payload = {
        success: false,
        invalidServerResponse: true
      };

      if (/<!doctype html|<html/i.test(text)) {
        payload.error = "پاسخ غیرمنتظره از سرور دریافت شد. صفحه را تازه‌سازی کنید.";
      } else if (status === 401) {
        payload.error = "نشست شما منقضی شده است.";
      } else if (status >= 500) {
        payload.error = "خطای داخلی سرور رخ داد.";
      } else {
        payload.error = "پاسخ معتبر از سرور دریافت نشد.";
      }
    }

    payload.httpStatus = status;
    if (!payload.success && !payload.error) {
      payload.error = status >= 500 ? "خطای داخلی سرور رخ داد." : "درخواست ناموفق بود.";
    }

    return payload;
  }

  async function apiRequest(method, action, payload, options) {
    var normalizedMethod = method === "POST" ? "POST" : "GET";
    var opts = asObject(options) || {};
    var quiet = opts.quiet === true;
    var requestPayload = Object.assign({ cohort: pageCohort }, payload || {});
    var requestOptions = {
      method: normalizedMethod,
      credentials: "same-origin",
      headers: {
        Accept: "application/json"
      }
    };
    var url = "/chat/chat_api.php";

    if (normalizedMethod === "GET") {
      var getParams = new URLSearchParams(Object.assign({ action: action }, requestPayload));
      url += "?" + getParams.toString();
    } else {
      requestOptions.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
      requestOptions.body = new URLSearchParams(Object.assign({ action: action }, requestPayload));
    }

    if (!quiet) setThreadUpdating(true);
    try {
      var response = await fetch(url, requestOptions);
      return parseApiResponse(response);
    } catch (error) {
      return {
        success: false,
        error: "ارتباط با سرور برقرار نشد.",
        networkError: true,
        httpStatus: 0
      };
    } finally {
      if (!quiet) setThreadUpdating(false);
    }
  }

  function apiGet(action, payload, options) {
    return apiRequest("GET", action, payload, options);
  }

  function apiPost(action, payload, options) {
    return apiRequest("POST", action, payload, options);
  }

  function offlineApi() {
    return window.Dent1402Site
      && typeof window.Dent1402Site === "object"
      && window.Dent1402Site.offline
      && typeof window.Dent1402Site.offline === "object"
      ? window.Dent1402Site.offline
      : null;
  }

  function queueOfflineChatSend(payload) {
    var offline = offlineApi();
    if (!offline || typeof offline.queueRequest !== "function" || !payload || !payload.conversationId) {
      return null;
    }
    return offline.queueRequest({
      kind: "chat-send",
      url: "/chat/chat_api.php",
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        Accept: "application/json"
      },
      body: new URLSearchParams(Object.assign({ action: "send", cohort: pageCohort }, payload)).toString(),
      dedupeKey: [
        "chat-send",
        pageCohort,
        String(payload.conversationId || ""),
        String(payload.text || ""),
        String(payload.replyTo || ""),
        Date.now().toString(36)
      ].join(":"),
      meta: {
        cohort: pageCohort,
        conversationId: String(payload.conversationId || "")
      }
    });
  }

  function applyQueuedChatSendPayload(payload) {
    if (!payload || payload.success !== true) {
      return false;
    }
    var message = normalizeMessage(payload.message);
    if (message && message.conversationId === state.activeConversationId) {
      appendMessages([message], {
        replaceAll: false,
        forceStick: true,
        smooth: true,
        markNew: true
      });
    }
    var nextConversation = normalizeConversation(payload.conversation);
    if (nextConversation) {
      upsertConversation(nextConversation);
      rebuildConversationsFromMap();
      renderConversationList();
      updateThreadHead();
    }
    setComposerStatus("", "");
    setConnectionState("live", "متصل");
    return true;
  }

  function consumeUnauthorized(payload, fallbackText) {
    var auth = safeAuthApi();
    var message = (payload && payload.error) || fallbackText || "نشست شما منقضی شده است. دوباره وارد شوید.";

    try {
      if (auth && typeof auth.handleUnauthorizedPayload === "function") {
        if (auth.handleUnauthorizedPayload(payload, message)) {
          handleUnauthorized(message);
          return true;
        }
      }
    } catch (error) {
      // Ignore cross-version auth surface errors and continue with fallback check.
    }

    if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
      if (auth && typeof auth.markUnauthorized === "function") {
        auth.markUnauthorized(message);
      }
      handleUnauthorized(message);
      return true;
    }

    return false;
  }

  function ensureSuccessResponse(response, fallbackText) {
    if (!response || response.success !== true) {
      throw new Error((response && response.error) || fallbackText || "درخواست ناموفق بود.");
    }
  }

  function showToast(text) {
    if (!toastEl || !text) return;
    toastEl.textContent = text;
    toastEl.classList.add("show");
    window.clearTimeout(state.toastTimer);
    state.toastTimer = window.setTimeout(function () {
      toastEl.classList.remove("show");
    }, 2200);
  }

  function motionEffectsEnabled() {
    if (document.documentElement.getAttribute("data-performance-mode") === "lite") {
      return false;
    }
    return !window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function softHaptic(pattern) {
    if (!navigator || typeof navigator.vibrate !== "function") return;
    try {
      navigator.vibrate(pattern);
    } catch (_error) {}
  }

  function touchLikePointer(pointerType) {
    return pointerType === "touch" || pointerType === "pen";
  }

  function interactiveMessageTarget(target) {
    var element = target && target.nodeType === 1
      ? target
      : (target && target.parentElement ? target.parentElement : null);
    if (!element || typeof element.closest !== "function") return false;
    return !!element.closest(
      "a, button, input, textarea, select, audio, video, summary, [role='button'], [contenteditable='true'], .msg-reaction, .reply-preview, .msg-attachment__media-btn, .msg-delivery-btn"
    );
  }

  function messageReplySwipeThresholdPx() {
    return clamp(Math.round(window.innerWidth * 0.11), 44, 64);
  }

  function messageReplySwipeLimitPx() {
    return clamp(Math.round(window.innerWidth * 0.16), 68, 96);
  }

  function setBubbleSwipeState(bubble, offsetPx, ready) {
    if (!bubble) return;
    var limit = messageReplySwipeLimitPx();
    var limitedOffset = clamp(toNumber(offsetPx, 0), 0, limit);
    var progress = limitedOffset / Math.max(1, limit);
    bubble.classList.add("is-swipe-tracking");
    bubble.classList.toggle("is-swipe-reply-ready", !!ready);
    bubble.style.setProperty("--msg-swipe-offset", limitedOffset.toFixed(1) + "px");
    bubble.style.setProperty("--msg-swipe-progress", progress.toFixed(3));
  }

  function clearBubbleSwipeState(bubble, options) {
    if (!bubble) return;
    var opts = asObject(options) || {};
    var acknowledge = !!opts.acknowledge;
    var settleMs = acknowledge ? 420 : 240;
    if (bubble.__chatSwipeResetTimer) {
      window.clearTimeout(bubble.__chatSwipeResetTimer);
      bubble.__chatSwipeResetTimer = 0;
    }
    bubble.classList.add("is-swipe-settling");
    bubble.classList.toggle("is-swipe-reply-fired", acknowledge);
    bubble.classList.remove("is-swipe-reply-ready");
    bubble.style.setProperty("--msg-swipe-offset", "0px");
    bubble.style.setProperty("--msg-swipe-progress", "0");
    bubble.__chatSwipeResetTimer = window.setTimeout(function () {
      bubble.classList.remove("is-swipe-tracking", "is-swipe-settling", "is-swipe-reply-ready", "is-swipe-reply-fired");
      bubble.style.removeProperty("--msg-swipe-offset");
      bubble.style.removeProperty("--msg-swipe-progress");
      bubble.__chatSwipeResetTimer = 0;
    }, settleMs);
  }

  function yieldChatFrame() {
    return new Promise(function (resolve) {
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(resolve);
      });
    });
  }

  function playReactionBurst(messageId, emoji) {
    if (!messagesEl) return;
    var row = messagesEl.querySelector('[data-mid="' + Number(messageId) + '"]');
    if (!row) return;
    var bubble = row.querySelector(".msg-bubble");
    if (!bubble) return;
    Array.from(bubble.querySelectorAll(".msg-heart-burst")).forEach(function (node) {
      node.remove();
    });
    var burst = document.createElement("span");
    burst.className = "msg-heart-burst";
    burst.setAttribute("aria-hidden", "true");
    burst.textContent = normalizeSpace(emoji) || TELEGRAM_HEART_REACTION;
    bubble.classList.add("is-gesture-reacting");
    bubble.appendChild(burst);
    var cleanup = function () {
      burst.removeEventListener("animationend", cleanup);
      if (burst.parentNode) {
        burst.parentNode.removeChild(burst);
      }
      bubble.classList.remove("is-gesture-reacting");
    };
    burst.addEventListener("animationend", cleanup);
    window.setTimeout(cleanup, motionEffectsEnabled() ? 720 : 80);
    softHaptic(motionEffectsEnabled() ? [10, 34, 14] : 8);
  }

  function setConnectionState(mode, text) {
    if (!connectionBadge) return;
    connectionBadge.dataset.state = mode || "idle";
    connectionBadge.textContent = text || "آفلاین";
  }

  function setBootState(text) {
    if (bootText) {
      bootText.textContent = text || "در حال بازیابی نشست...";
    }
  }

  function showGuardMessage(text, kind) {
    if (!msgBox) return;
    msgBox.textContent = text || "";
    msgBox.className = "login-feedback" + (kind ? " " + kind : "");
  }

  function showStreamState(kind, title, desc) {
    if (!streamStateEl) return;
    if (!kind) {
      streamStateEl.hidden = true;
      streamStateEl.innerHTML = "";
      delete streamStateEl.dataset.kind;
      return;
    }
    streamStateEl.dataset.kind = normalizeSpace(kind) || "info";
    streamStateEl.hidden = false;
    streamStateEl.innerHTML =
      "<strong>" + escapeHtml(title || "") + "</strong>" +
      (desc ? "<span>" + escapeHtml(desc) + "</span>" : "");
  }

  function setComposerStatus(text, kind) {
    if (!composerStatus) return;
    composerStatus.textContent = text || "";
    composerStatus.className = "composer-status" + (kind ? " " + kind : "");
  }

  function hideAllStages() {
    bootBox.hidden = true;
    loginBox.hidden = true;
    chatBox.hidden = true;
  }

  function applyViewportHeight() {
    var fallbackHeight = Math.max(0, Math.round(window.innerHeight || 0));
    var viewportHeight = fallbackHeight;
    if (window.visualViewport) {
      var vvHeight = Math.max(0, Math.round(window.visualViewport.height || 0));
      var vvOffsetTop = Math.max(0, Math.round(window.visualViewport.offsetTop || 0));
      if (vvHeight > 0) {
        viewportHeight = vvHeight + vvOffsetTop;
      }
    }
    if (viewportHeight < 320) {
      viewportHeight = fallbackHeight > 0 ? fallbackHeight : 320;
    }
    document.documentElement.style.setProperty("--chat-vh", Math.round(viewportHeight) + "px");
    if (document.body) {
      document.body.classList.toggle("chat-keyboard-open", isKeyboardViewportShift());
    }
    syncThemeColor();
  }

  function isMobileViewport() {
    return window.matchMedia("(max-width: 980px)").matches;
  }

  function isKeyboardViewportShift() {
    if (!isMobileViewport() || !window.visualViewport) {
      return false;
    }
    var vv = window.visualViewport;
    var delta = Math.max(0, Math.round(window.innerHeight - vv.height));
    return delta >= 140;
  }

  function queueViewportRefresh(syncComposerIntoView) {
    viewportRefreshNeedsComposerSync = viewportRefreshNeedsComposerSync || !!syncComposerIntoView;
    if (viewportRefreshFrame) {
      return;
    }

    viewportRefreshFrame = window.requestAnimationFrame(function () {
      var shouldSyncComposer = viewportRefreshNeedsComposerSync;
      viewportRefreshFrame = 0;
      viewportRefreshNeedsComposerSync = false;
      applyViewportHeight();
      if (shouldSyncComposer) {
        syncFocusedComposerIntoView();
      }
    });
  }

  function installChatOverscrollGuard() {
    var touchStartY = 0;
    var activeScroller = null;
    var scrollerSelector = ".messages, .thread-stream, .conversation-list, .chat-modal__body, .chat-info-sheet__body, .chat-options-member-list";

    document.addEventListener("touchstart", function (event) {
      if (!isMobileViewport() || !event.touches || !event.touches.length) {
        activeScroller = null;
        return;
      }
      touchStartY = event.touches[0].clientY;

      // Normalize event.target to an Element in case the touch landed on a text node
      var targetEl = event.target;
      if (targetEl && targetEl.nodeType !== 1 && targetEl.parentElement) {
        targetEl = targetEl.parentElement;
      }

      activeScroller = targetEl && typeof targetEl.closest === "function" ? targetEl.closest(scrollerSelector) : null;
    }, { passive: true });

    document.addEventListener("touchmove", function (event) {
      if (!isMobileViewport() || !event.touches || !event.touches.length) {
        return;
      }

      var currentY = event.touches[0].clientY;
      var deltaY = currentY - touchStartY;
      // If no scroller was found at touchstart, try to locate it on move.
      if (!activeScroller) {
        var moveTarget = event.target;
        if (moveTarget && moveTarget.nodeType !== 1 && moveTarget.parentElement) {
          moveTarget = moveTarget.parentElement;
        }
        activeScroller = moveTarget && typeof moveTarget.closest === "function" ? moveTarget.closest(scrollerSelector) : null;
        if (!activeScroller) {
          return;
        }
      }

      var maxScrollTop = Math.max(0, activeScroller.scrollHeight - activeScroller.clientHeight);
      if (maxScrollTop <= 0) {
        if (event.cancelable) {
          event.preventDefault();
        }
        return;
      }

      var atTop = activeScroller.scrollTop <= 0;
      var atBottom = activeScroller.scrollTop >= maxScrollTop - 1;
      // deltaY > 0 means scrolling down (towards bottom)
      if ((atTop && deltaY > 0) || (atBottom && deltaY < 0)) {
        if (event.cancelable) {
          event.preventDefault();
        }
      }
    }, { passive: false });

    document.addEventListener("touchend", function () {
      activeScroller = null;
    }, { passive: true });

    // Desktop: prevent wheel from bubbling out when over scroll boundaries
    document.addEventListener("wheel", function (event) {
      if (!event || event.defaultPrevented) return;
      var targetEl = event.target;
      if (targetEl && targetEl.nodeType !== 1 && targetEl.parentElement) {
        targetEl = targetEl.parentElement;
      }
      var scroller = targetEl && typeof targetEl.closest === "function" ? targetEl.closest(scrollerSelector) : null;
      if (!scroller) return;

      var maxScrollTop = Math.max(0, scroller.scrollHeight - scroller.clientHeight);
      if (maxScrollTop <= 0) {
        event.preventDefault();
        return;
      }

      var deltaY = event.deltaY || 0;
      var atTop = scroller.scrollTop <= 0;
      var atBottom = scroller.scrollTop >= maxScrollTop - 1;
      // deltaY > 0 -> scrolling down; deltaY < 0 -> scrolling up
      if ((atTop && deltaY < 0) || (atBottom && deltaY > 0)) {
        event.preventDefault();
        return;
      }
    }, { passive: false });
  }

  function syncFocusedComposerIntoView() {
    if (!chatTextEl || document.activeElement !== chatTextEl || !isMobileViewport()) {
      return;
    }
    window.requestAnimationFrame(function () {
      autosizeComposer();
      try {
        chatTextEl.scrollIntoView({ block: "nearest", inline: "nearest" });
      } catch (error) {}
      if (messagesEl && state.threadAutoStick) {
        messagesEl.scrollTo({
          top: messagesEl.scrollHeight,
          behavior: "auto"
        });
      }
    });
  }

  function normalizeConversationListCategory(value) {
    var category = normalizeSpace(value || "").toLowerCase();
    if (category === "unread" || category === "groups" || category === "direct") {
      return category;
    }
    return "all";
  }

  function updateConversationFilterTabs() {
    if (!conversationFilterTabs) return;
    var activeCategory = normalizeConversationListCategory(state.conversationListCategory);
    var buttons = Array.from(conversationFilterTabs.querySelectorAll("[data-list-filter]"));
    buttons.forEach(function (button) {
      var isActive = normalizeConversationListCategory(button.getAttribute("data-list-filter")) === activeCategory;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-selected", isActive ? "true" : "false");
      if (isActive) {
        button.setAttribute("tabindex", "0");
      } else {
        button.setAttribute("tabindex", "-1");
      }
    });
    if (isMobileViewport()) {
      var activeButton = buttons.find(function (button) {
        return button.classList.contains("is-active");
      });
      if (activeButton && typeof activeButton.scrollIntoView === "function") {
        try {
          activeButton.scrollIntoView({ behavior: "smooth", inline: "center", block: "nearest" });
        } catch (error) {}
      }
    }
  }

  function setConversationListCategory(value) {
    var next = normalizeConversationListCategory(value);
    if (state.conversationListCategory === next) {
      updateConversationFilterTabs();
      return;
    }
    state.conversationListCategory = next;
    updateConversationFilterTabs();
    renderConversationList();
    scheduleFastChatCacheSave(120);
  }

  function conversationMatchesListCategory(conversation) {
    if (!conversation) return false;
    var category = normalizeConversationListCategory(state.conversationListCategory);
    if (category === "unread") {
      return Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0))) > 0;
    }
    if (category === "groups") {
      return !isPrivateLikeConversationType(conversation.type);
    }
    if (category === "direct") {
      return isPrivateLikeConversationType(conversation.type);
    }
    return true;
  }

  function parseRgbChannels(value) {
    var raw = normalizeSpace(value || "");
    if (!raw) return null;

    var hexMatch = raw.match(/^#([0-9a-f]{3,8})$/i);
    if (hexMatch) {
      var hex = hexMatch[1];
      if (hex.length === 3 || hex.length === 4) {
        return [
          parseInt(hex.charAt(0) + hex.charAt(0), 16),
          parseInt(hex.charAt(1) + hex.charAt(1), 16),
          parseInt(hex.charAt(2) + hex.charAt(2), 16)
        ];
      }
      if (hex.length >= 6) {
        return [
          parseInt(hex.slice(0, 2), 16),
          parseInt(hex.slice(2, 4), 16),
          parseInt(hex.slice(4, 6), 16)
        ];
      }
    }

    var rgbMatch = raw.match(/rgba?\(\s*([0-9.]+)[,\s]+([0-9.]+)[,\s]+([0-9.]+)/i);
    if (!rgbMatch) return null;
    return [
      clamp(Math.round(toNumber(rgbMatch[1], 0)), 0, 255),
      clamp(Math.round(toNumber(rgbMatch[2], 0)), 0, 255),
      clamp(Math.round(toNumber(rgbMatch[3], 0)), 0, 255)
    ];
  }

  function resolveThemeColorValue() {
    if (!document.body) return "";
    var styles = window.getComputedStyle(document.body);
    var candidateColors = [
      styles.getPropertyValue("--chat-statusbar-color"),
      styles.getPropertyValue("--chat-top-chrome-color"),
      styles.getPropertyValue("--chat-header-bg"),
      styles.getPropertyValue("--chat-pane-bg-strong")
    ];

    for (var i = 0; i < candidateColors.length; i += 1) {
      var candidate = normalizeSpace(candidateColors[i]);
      if (!candidate) continue;
      var channels = parseRgbChannels(candidate);
      if (channels) {
        return "rgb(" + channels[0] + ", " + channels[1] + ", " + channels[2] + ")";
      }
      if (candidate.charAt(0) === "#") {
        return candidate;
      }
    }
    return "";
  }

  function syncThemeColor() {
    if ((!themeColorMetas || !themeColorMetas.length) && !appleStatusBarMeta) return;
    var color = resolveThemeColorValue();
    if (!color) return;

    themeColorMetas.forEach(function (meta) {
      if (!meta) return;
      meta.setAttribute("content", color);
    });

    if (appleStatusBarMeta) {
      var channels = parseRgbChannels(color);
      if (channels) {
        var luminance = (0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]) / 255;
        appleStatusBarMeta.setAttribute("content", luminance < 0.55 ? "black-translucent" : "default");
      }
    }
  }

  function canOpenPollCenterForUser(user) {
    var source = asObject(user);
    if (!source) return false;
    var role = normalizeSpace(source.role);
    return role === "owner" || role === "representative" || !!source.isOwner || !!source.isRepresentative;
  }

  function canCurrentUserViewStudentNumbers() {
    return !!state.me.loggedIn && canOpenPollCenterForUser(state.me);
  }

  function userRoleMetaText(user) {
    var source = asObject(user) || {};
    var roleLabel = normalizeSpace(source.roleLabel) || "دانشجو";
    var studentNumber = normalizeStudentNumber(source.studentNumber || source.username);
    if (canCurrentUserViewStudentNumbers() && studentNumber) {
      return roleLabel + "  " + studentNumber;
    }
    return roleLabel;
  }

  function displayNameFallback(studentNumber) {
    var normalizedStudentNumber = normalizeStudentNumber(studentNumber);
    if (canCurrentUserViewStudentNumbers() && normalizedStudentNumber) {
      return normalizedStudentNumber;
    }
    return "\u06a9\u0627\u0631\u0628\u0631";
  }


  function setMobileView(view) {
    if (!chatApp) return;
    var allowed = ["list", "thread", "dm", "group"];
    var next = allowed.indexOf(view) !== -1 ? view : "list";
    if (!isMobileViewport() && (next === "dm" || next === "group")) {
      next = "list";
    }
    chatApp.dataset.mobileView = next;
    updateFabVisibility();
    updateMobileNav();
    syncThemeColor();
  }

  function openListPane() {
    if (state.modalOpen) {
      closeModal();
    }
    setMobileView("list");
  }

  function openThreadPane() {
    if (isMobileViewport()) {
      setMobileView("thread");
    }
  }

  function updateFabVisibility() {
    if (!mobileNewChatFab) return;
    var hasMessengerNav = !!chatMobileNav;
    var shouldShow = !!state.me.loggedIn && isMobileViewport() && chatBox && !chatBox.hidden && !state.modalOpen && !hasMessengerNav;
    mobileNewChatFab.hidden = !shouldShow;
  }

  function setMobileNavItemActive(node, active) {
    if (!node) return;
    node.classList.toggle("is-active", !!active);
  }

  function updateMobileNav() {
    if (!chatMobileNav) {
      if (document.body) {
        document.body.classList.remove("chat-mobile-nav-visible");
      }
      syncThemeColor();
      return;
    }
    var loggedIn = !!state.me.loggedIn && chatBox && !chatBox.hidden;
    var mobile = isMobileViewport();
    var listViewActive = chatApp && chatApp.dataset.mobileView === "list";
    var shouldShow = loggedIn && mobile && !state.modalOpen && !state.contextOpen && !state.listContextOpen && !state.infoSheetOpen && !state.peerSheetOpen && listViewActive;
    chatMobileNav.hidden = !shouldShow;
    if (document.body) {
      document.body.classList.toggle("chat-mobile-nav-visible", shouldShow);
    }
    if (!shouldShow) {
      setMobileNavItemActive(chatNavList, false);
      setMobileNavItemActive(chatNavCompose, false);
      setMobileNavItemActive(chatNavGroup, false);
      setMobileNavItemActive(chatNavHome, false);
      setMobileNavItemActive(chatNavSettings, false);
      syncThemeColor();
      return;
    }

    var hasDmModal = state.modalOpen === "dm";
    var hasGroupModal = state.modalOpen === "group";
    setMobileNavItemActive(chatNavList, true);
    setMobileNavItemActive(chatNavCompose, hasDmModal);
    setMobileNavItemActive(chatNavGroup, hasGroupModal);
    setMobileNavItemActive(chatNavHome, false);
    setMobileNavItemActive(chatNavSettings, false);
    syncThemeColor();
  }

  function selectedConversations() {
    return Array.from(state.selectedConversationIds)
      .map(function (conversationId) { return state.conversationsById.get(conversationId) || null; })
      .filter(Boolean);
  }

  function selectableConversationIds() {
    var list = filteredConversations();
    var buckets = splitConversationBuckets(list);
    var hasQuery = !!normalizeSpace(state.conversationFilter);
    var visible = buckets.active.slice();
    if (state.showArchivedConversations || hasQuery) {
      visible = visible.concat(buckets.archived);
    }
    return Array.from(new Set(visible.map(function (conversation) {
      return conversation && conversation.id ? conversation.id : "";
    }).filter(Boolean)));
  }

  function updateSelectionUi() {
    if (document.body) {
      document.body.classList.toggle("chat-selection-mode", !!state.listSelectionMode);
    }
    if (conversationManageBar) {
      conversationManageBar.hidden = !state.listSelectionMode;
    }
    if (document.body) {
      document.body.classList.toggle("chat-list-selection-mode", !!state.listSelectionMode);
    }
    if (conversationSelectionCount) {
      var count = state.selectedConversationIds.size;
      conversationSelectionCount.textContent = count.toLocaleString("fa-IR") + " مورد انتخاب";
    }
    if (conversationSelectionToggleAll) {
      var selectableIds = selectableConversationIds();
      var selectableCount = selectableIds.length;
      var selectedVisibleCount = selectableIds.reduce(function (count, conversationId) {
        return count + (state.selectedConversationIds.has(conversationId) ? 1 : 0);
      }, 0);
      var allSelected = selectableCount > 0 && selectedVisibleCount >= selectableCount;
      conversationSelectionToggleAll.disabled = selectableCount <= 0;
      conversationSelectionToggleAll.textContent = allSelected ? "لغو همه" : "انتخاب همه";
    }
    var hasSelection = state.selectedConversationIds.size > 0;
    [
      conversationBatchRead,
      conversationBatchUnread,
      conversationBatchPin,
      conversationBatchUnpin,
      conversationBatchArchive,
      conversationBatchUnarchive,
      conversationBatchMute,
      conversationBatchUnmute,
      conversationBatchDelete
    ].forEach(function (button) {
      if (!button) return;
      button.disabled = !hasSelection;
    });
  }

  function enterConversationSelectionMode(seedConversationId) {
    state.listSelectionMode = true;
    if (seedConversationId) {
      state.selectedConversationIds.add(seedConversationId);
    }
    closeListContextMenu();
    updateSelectionUi();
    renderConversationList();
  }

  function exitConversationSelectionMode() {
    state.listSelectionMode = false;
    state.selectedConversationIds.clear();
    updateSelectionUi();
    renderConversationList();
  }

  function toggleConversationSelection(conversationId, force) {
    var normalizedId = normalizeSpace(conversationId);
    if (!normalizedId) return;
    var shouldSelect = force === true;
    var shouldUnselect = force === false;
    if (!shouldSelect && !shouldUnselect) {
      shouldSelect = !state.selectedConversationIds.has(normalizedId);
    }
    if (shouldSelect) {
      state.selectedConversationIds.add(normalizedId);
    } else {
      state.selectedConversationIds.delete(normalizedId);
    }
    updateSelectionUi();
    renderConversationList();
  }

  function toggleSelectAllVisibleConversations() {
    if (!state.listSelectionMode) {
      state.listSelectionMode = true;
    }
    var ids = selectableConversationIds();
    if (!ids.length) {
      updateSelectionUi();
      renderConversationList();
      return;
    }
    var allSelected = ids.every(function (conversationId) {
      return state.selectedConversationIds.has(conversationId);
    });
    ids.forEach(function (conversationId) {
      if (allSelected) {
        state.selectedConversationIds.delete(conversationId);
      } else {
        state.selectedConversationIds.add(conversationId);
      }
    });
    updateSelectionUi();
    renderConversationList();
  }

  function selectedMessages() {
    return Array.from(state.selectedMessageIds)
      .map(function (messageId) { return findMessage(messageId); })
      .filter(function (message) {
        return !!message && normalizeSpace(message.conversationId) === normalizeSpace(state.activeConversationId);
      })
      .sort(function (left, right) {
        return left.id - right.id;
      });
  }

  function selectableMessageIds() {
    return messageList()
      .filter(function (message) {
        return !!message && normalizeSpace(message.conversationId) === normalizeSpace(state.activeConversationId);
      })
      .map(function (message) {
        return Math.max(0, Math.floor(toNumber(message.id, 0)));
      })
      .filter(function (messageId) {
        return messageId > 0;
      });
  }

  function pruneSelectedMessageIds() {
    var validIds = new Set();
    state.selectedMessageIds.forEach(function (messageId) {
      var message = findMessage(messageId);
      if (message && normalizeSpace(message.conversationId) === normalizeSpace(state.activeConversationId)) {
        validIds.add(message.id);
      }
    });
    state.selectedMessageIds = validIds;
    if (!state.selectedMessageIds.size) {
      state.messageSelectionMode = false;
    }
  }

  function syncSelectedMessageNodes() {
    if (!messagesEl) return;
    Array.from(messagesEl.querySelectorAll(".msg-item[data-mid]")).forEach(function (item) {
      var messageId = Math.max(0, Math.floor(toNumber(item.getAttribute("data-mid"), 0)));
      var isSelected = state.selectedMessageIds.has(messageId);
      item.classList.toggle("is-selected", isSelected);
      item.classList.toggle("is-selection-mode", !!state.messageSelectionMode);
      var badge = item.querySelector(".msg-select-badge");
      if (badge) {
        badge.setAttribute("aria-hidden", "true");
      }
    });
  }

  function updateMessageSelectionUi() {
    pruneSelectedMessageIds();
    if (document.body) {
      document.body.classList.toggle("chat-message-selection-mode", !!state.messageSelectionMode);
    }
    if (messageManageBar) {
      messageManageBar.hidden = !state.messageSelectionMode;
    }
    if (messageSelectionCount) {
      messageSelectionCount.textContent = state.selectedMessageIds.size.toLocaleString("fa-IR") + " مورد انتخاب";
    }
    if (messageSelectionToggleAll) {
      var selectableIds = selectableMessageIds();
      var allSelected = selectableIds.length > 0 && selectableIds.every(function (messageId) {
        return state.selectedMessageIds.has(messageId);
      });
      messageSelectionToggleAll.disabled = selectableIds.length <= 0;
      messageSelectionToggleAll.textContent = allSelected ? "لغو همه" : "انتخاب همه";
    }
    var selected = selectedMessages();
    var hasSelection = selected.length > 0;
    var canPin = canPinMessage();
    var hasDeletableSelection = selected.some(function (message) {
      return canManageMessage(message);
    });
    [
      messageBatchCopy,
      messageBatchForward
    ].forEach(function (button) {
      if (!button) return;
      button.disabled = !hasSelection;
    });
    [
      messageBatchPin,
      messageBatchUnpin
    ].forEach(function (button) {
      if (!button) return;
      button.disabled = !hasSelection || !canPin;
    });
    if (messageBatchDelete) {
      messageBatchDelete.disabled = !hasDeletableSelection;
    }
    syncSelectedMessageNodes();
    updateComposerState();
  }

  function enterMessageSelectionMode(seedMessageId) {
    state.messageSelectionMode = true;
    if (seedMessageId) {
      state.selectedMessageIds.add(Math.max(0, Math.floor(toNumber(seedMessageId, 0))));
    }
    closeContextMenu();
    updateMessageSelectionUi();
  }

  function exitMessageSelectionMode() {
    state.messageSelectionMode = false;
    state.selectedMessageIds.clear();
    updateMessageSelectionUi();
  }

  function toggleMessageSelection(messageId, force) {
    var numericId = Math.max(0, Math.floor(toNumber(messageId, 0)));
    if (numericId <= 0) return;
    if (!state.messageSelectionMode) {
      state.messageSelectionMode = true;
    }
    var shouldSelect = force === true;
    var shouldUnselect = force === false;
    if (!shouldSelect && !shouldUnselect) {
      shouldSelect = !state.selectedMessageIds.has(numericId);
    }
    if (shouldSelect) {
      state.selectedMessageIds.add(numericId);
    } else {
      state.selectedMessageIds.delete(numericId);
    }
    updateMessageSelectionUi();
  }

  function toggleSelectAllVisibleMessages() {
    if (!state.messageSelectionMode) {
      state.messageSelectionMode = true;
    }
    var ids = selectableMessageIds();
    if (!ids.length) {
      updateMessageSelectionUi();
      return;
    }
    var allSelected = ids.every(function (messageId) {
      return state.selectedMessageIds.has(messageId);
    });
    ids.forEach(function (messageId) {
      if (allSelected) {
        state.selectedMessageIds.delete(messageId);
      } else {
        state.selectedMessageIds.add(messageId);
      }
    });
    updateMessageSelectionUi();
  }

  function reactionUsageScore(emoji) {
    return Math.max(0, Math.floor(toNumber(state.reactionUsage.get(emoji), 0)));
  }

  function pushRecentReaction(emoji) {
    if (!isLikelyEmoji(emoji)) return;
    state.recentReactions = [emoji].concat(state.recentReactions.filter(function (item) {
      return item !== emoji;
    })).slice(0, REACTION_RECENTS_LIMIT);
    state.reactionUsage.set(emoji, reactionUsageScore(emoji) + 1);
    saveReactionPreferences();
  }

  function activeReactionGroups(sourceMessage) {
    var conversation = activeConversation();
    if (conversation && conversation.settings && conversation.settings.reactionMode === "none") {
      return [];
    }
    var groups = REACTION_GROUPS.map(function (group) {
      var emojis = group.id === "recent"
        ? state.recentReactions.concat(reactionEntries(sourceMessage).map(function (entry) { return entry.emoji; }))
        : (Array.isArray(group.emojis) ? group.emojis : []);
      var unique = Array.from(new Set(emojis.filter(isLikelyEmoji)));
      return {
        id: group.id,
        label: group.label,
        emojis: unique
      };
    }).filter(function (group) {
      return group.id !== "recent" || group.emojis.length > 0;
    });
    var mode = conversation && conversation.settings ? conversation.settings.reactionMode : "all";
    groups.unshift({ id: "all", label: "همه", emojis: mode === "quick" ? QUICK_REACTIONS.slice() : REACTIONS.slice() });
    return groups;
  }

  function reactionAllowedInActiveConversation(emoji) {
    var conversation = activeConversation();
    var mode = conversation && conversation.settings ? conversation.settings.reactionMode : "all";
    if (mode === "none") return false;
    if (mode === "quick") return QUICK_REACTIONS.indexOf(emoji) !== -1;
    return true;
  }

  function quickReactionsForMessage(message) {
    var conversation = activeConversation();
    if (conversation && conversation.settings && conversation.settings.reactionMode === "none") {
      return [];
    }
    var ordered = [];
    reactionEntries(message).forEach(function (entry) {
      if (!isLikelyEmoji(entry.emoji)) return;
      ordered.push(entry.emoji);
    });
    state.recentReactions.forEach(function (emoji) {
      if (!isLikelyEmoji(emoji)) return;
      ordered.push(emoji);
    });
    QUICK_REACTIONS.forEach(function (emoji) {
      ordered.push(emoji);
    });
    var unique = [];
    ordered.forEach(function (emoji) {
      if (unique.indexOf(emoji) !== -1 || !reactionAllowedInActiveConversation(emoji)) return;
      unique.push(emoji);
    });
    unique.sort(function (left, right) {
      return reactionUsageScore(right) - reactionUsageScore(left);
    });
    return unique.slice(0, 12);
  }

  function updateGroupSelectionMeta() {
    if (!groupSelectionMeta) return;
    groupSelectionMeta.textContent = state.groupMemberSelection.size > 0
      ? state.groupMemberSelection.size.toLocaleString("fa-IR") + " عضو انتخاب شد"
      : "هنوز عضوی انتخاب نشده است.";
  }

  function renderGroupSelectedMembers() {
    if (!groupSelectedMembers) return;
    var selectedUsers = usersForDirectory().filter(function (user) {
      return state.groupMemberSelection.has(user.studentNumber);
    });
    if (!selectedUsers.length) {
      groupSelectedMembers.innerHTML = "";
      groupSelectedMembers.hidden = true;
      return;
    }

    groupSelectedMembers.hidden = false;
    groupSelectedMembers.innerHTML = selectedUsers.map(function (user) {
      return [
        '<button class="group-selected-chip" type="button" data-remove-member="' + escapeHtml(user.studentNumber) + '" aria-label="حذف ' + escapeHtml(user.name) + '">',
        "  <span>" + escapeHtml(user.name) + "</span>",
        "  <strong></strong>",
        "</button>"
      ].join("");
    }).join("");
  }

  function setGroupCreateStep(step) {
    var next = step === "details" ? "details" : "members";
    state.groupCreateStep = next;
    if (groupStepMembers) {
      var membersActive = next === "members";
      groupStepMembers.hidden = !membersActive;
      groupStepMembers.classList.toggle("is-active", membersActive);
    }
    if (groupStepDetails) {
      var detailsActive = next === "details";
      groupStepDetails.hidden = !detailsActive;
      groupStepDetails.classList.toggle("is-active", detailsActive);
    }
    if (groupModalSubtitle) {
      groupModalSubtitle.textContent = next === "members" ? "۱ از ۲ • انتخاب اعضا" : "۲ از ۲ • اطلاعات گروه";
    }
    if (groupStepBack) {
      groupStepBack.hidden = next !== "details";
    }
    if (groupStepNext) {
      groupStepNext.hidden = next !== "members";
    }
    if (groupCreateBtn) {
      groupCreateBtn.hidden = next !== "details";
    }
  }

  function resetGroupCreateFlow() {
    setGroupCreateStep("members");
    state.groupMemberSelection.clear();
    if (groupTitleInput) groupTitleInput.value = "";
    if (groupAboutInput) groupAboutInput.value = "";
    if (groupKindGroupInput) groupKindGroupInput.checked = true;
    if (groupKindChannelInput) groupKindChannelInput.checked = false;
    if (groupSearch) groupSearch.value = "";
    renderGroupSelectedMembers();
    updateGroupSelectionMeta();
    renderGroupMembersPicker();
  }

  function sortConversations(list) {
    return list.slice().sort(function (a, b) {
      if (!!a.isMandatory !== !!b.isMandatory) return a.isMandatory ? -1 : 1;
      var aArchived = !!(a.viewerState && a.viewerState.archived);
      var bArchived = !!(b.viewerState && b.viewerState.archived);
      if (aArchived !== bArchived) return aArchived ? 1 : -1;
      var aPinned = !!(a.viewerState && a.viewerState.pinned);
      var bPinned = !!(b.viewerState && b.viewerState.pinned);
      if (aPinned !== bPinned) return aPinned ? -1 : 1;
      if (aPinned && bPinned) {
        var aPinnedAt = toNumber(a.viewerState && a.viewerState.pinnedAt, 0);
        var bPinnedAt = toNumber(b.viewerState && b.viewerState.pinnedAt, 0);
        if (aPinnedAt !== bPinnedAt) return bPinnedAt - aPinnedAt;
      }
      var aUnread = toNumber(a.unreadCount, 0);
      var bUnread = toNumber(b.unreadCount, 0);
      if (aUnread > 0 && bUnread === 0) return -1;
      if (bUnread > 0 && aUnread === 0) return 1;
      var aUpdated = toNumber(a.updatedAt, 0);
      var bUpdated = toNumber(b.updatedAt, 0);
      if (aUpdated !== bUpdated) return bUpdated - aUpdated;
      return toText(a.title).localeCompare(toText(b.title), "fa");
    });
  }

  function normalizeUser(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var studentNumber = normalizeStudentNumber(source.studentNumber || source.username);
    if (!studentNumber) return null;
    var profile = asObject(source.profile) || {};
    return {
      studentNumber: studentNumber,
      name: normalizeSpace(source.name) || displayNameFallback(studentNumber),
      role: normalizeSpace(source.role) || "student",
      roleLabel: normalizeSpace(source.roleLabel) || "دانشجو",
      canModerateChat: !!source.canModerateChat,
      isOwner: !!source.isOwner || normalizeSpace(source.role) === "owner",
      isRepresentative: !!source.isRepresentative || normalizeSpace(source.role) === "representative",
      isConversationAdmin: !!source.isConversationAdmin,
      isConversationCreator: !!source.isConversationCreator,
      conversationRole: normalizeSpace(source.conversationRole) || "",
      conversationTag: normalizeSpace(source.conversationTag),
      profile: {
        avatarUrl: normalizeAvatarUrl(profile.avatarUrl || source.avatarUrl || ""),
        about: normalizeSpace(profile.about || profile.bio || source.about || "")
      },
      presence: normalizePresenceState(source.presence || {}, "")
    };
  }

  function normalizeMentionUsers(raw) {
    return (Array.isArray(raw) ? raw : []).map(normalizeUser).filter(Boolean);
  }

  function normalizePollOption(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var id = normalizeSpace(source.id);
    if (!id) return null;
    return {
      id: id,
      text: normalizeSpace(source.text) || "گزینه",
      isSelected: !!source.isSelected,
      voteCount: source.voteCount == null ? null : Math.max(0, Math.floor(toNumber(source.voteCount, 0))),
      percent: source.percent == null ? null : Math.max(0, toNumber(source.percent, 0)),
      voters: normalizeMentionUsers(source.voters)
    };
  }

  function normalizePoll(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var id = normalizeSpace(source.id);
    if (!id) return null;
    var settings = asObject(source.settings) || {};
    var viewer = asObject(source.viewer) || {};
    var results = asObject(source.results) || {};
    var permissions = asObject(source.permissions) || {};
    var audience = asObject(source.audience) || {};
    var conversation = asObject(source.conversation) || {};
    return {
      id: id,
      question: normalizeSpace(source.question),
      shareUrl: normalizeSpace(source.shareUrl),
      status: normalizeSpace(source.status || "open"),
      messageId: source.messageId != null ? Math.max(0, Math.floor(toNumber(source.messageId, 0))) : 0,
      createdAt: Math.max(0, Math.floor(toNumber(source.createdAt, 0))),
      updatedAt: Math.max(0, Math.floor(toNumber(source.updatedAt, 0))),
      startAt: source.startAt != null ? Math.max(0, Math.floor(toNumber(source.startAt, 0))) : null,
      endAt: source.endAt != null ? Math.max(0, Math.floor(toNumber(source.endAt, 0))) : null,
      closedAt: source.closedAt != null ? Math.max(0, Math.floor(toNumber(source.closedAt, 0))) : null,
      createdBy: normalizeUser(source.createdBy),
      closedBy: normalizeUser(source.closedBy),
      audience: {
        mode: normalizeSpace(audience.mode || settings.audience || "link"),
        label: normalizeSpace(audience.label),
        discoverable: !!audience.discoverable
      },
      conversation: normalizeSpace(conversation.id) ? {
        id: normalizeSpace(conversation.id),
        type: normalizeSpace(conversation.type || "group"),
        title: normalizeSpace(conversation.title)
      } : null,
      settings: {
        anonymous: settings.anonymous !== false,
        multipleChoice: !!settings.multipleChoice,
        maxChoices: Math.max(1, Math.floor(toNumber(settings.maxChoices, 1))),
        allowVoteChange: settings.allowVoteChange !== false,
        allowCreatorVote: settings.allowCreatorVote !== false,
        resultVisibility: normalizeSpace(settings.resultVisibility || "live"),
        audience: normalizeSpace(settings.audience || audience.mode || "link")
      },
      viewer: {
        hasVoted: !!viewer.hasVoted,
        selectedOptionIds: Array.from(new Set((Array.isArray(viewer.selectedOptionIds) ? viewer.selectedOptionIds : []).map(function (item) {
          return normalizeSpace(item);
        }).filter(Boolean))),
        canVote: !!viewer.canVote,
        canChangeVote: !!viewer.canChangeVote,
        isCreator: !!viewer.isCreator,
        isInAudience: viewer.isInAudience !== false,
        canAccess: viewer.canAccess !== false
      },
      results: {
        visible: !!results.visible,
        identitiesVisible: !!results.identitiesVisible,
        hiddenReason: normalizeSpace(results.hiddenReason),
        totalVoters: results.totalVoters == null ? null : Math.max(0, Math.floor(toNumber(results.totalVoters, 0))),
        totalVotes: results.totalVotes == null ? null : Math.max(0, Math.floor(toNumber(results.totalVotes, 0)))
      },
      permissions: {
        canManage: !!permissions.canManage,
        canClose: !!permissions.canClose,
        canReopen: !!permissions.canReopen
      },
      options: (Array.isArray(source.options) ? source.options : []).map(normalizePollOption).filter(Boolean)
    };
  }

  function normalizeAttachment(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var id = normalizeSpace(source.id);
    if (!id) return null;
    var category = normalizeAttachmentCategory(source.category);
    var mime = normalizeSpace(source.mime).toLowerCase();
    var available = source.available !== false && !source.expired;
    var previewUrl = toText(source.previewUrl || "");
    var url = toText(source.url || "");
    var downloadUrl = toText(source.downloadUrl || "");

    return {
      id: id,
      messageId: Math.max(0, Math.floor(toNumber(source.messageId, 0))),
      conversationId: normalizeSpace(source.conversationId),
      category: category,
      name: normalizeSpace(source.name) || normalizeSpace(source.safeFileName) || "file",
      safeFileName: normalizeSpace(source.safeFileName),
      mime: mime,
      extension: normalizeSpace(source.extension).toLowerCase(),
      sizeBytes: Math.max(0, Math.floor(toNumber(source.sizeBytes, 0))),
      durationSeconds: source.durationSeconds != null ? Math.max(0, toNumber(source.durationSeconds, 0)) : null,
      available: !!available,
      expired: !available || !!source.expired,
      hasPreview: !!source.hasPreview || !!previewUrl,
      status: normalizeSpace(source.status || (available ? "available" : "expired")) || "available",
      purgedAt: source.purgedAt != null ? Math.floor(toNumber(source.purgedAt, 0)) : null,
      purgeReason: normalizeSpace(source.purgeReason),
      url: url,
      previewUrl: previewUrl,
      downloadUrl: downloadUrl,
      isVoice: !!source.isVoice || category === "voice"
    };
  }

  function normalizeForwardedFrom(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var conversationId = normalizeSpace(source.conversationId);
    var messageId = Math.max(0, Math.floor(toNumber(source.messageId, 0)));
    if (!conversationId || messageId <= 0) return null;
    var messageKind = normalizeSpace(source.messageKind || "text");
    if (messageKind !== "poll" && messageKind !== "attachment" && messageKind !== "voice") {
      messageKind = "text";
    }
    return {
      conversationId: conversationId,
      messageId: messageId,
      senderStudentNumber: normalizeStudentNumber(source.senderStudentNumber || ""),
      senderName: normalizeSpace(source.senderName || ""),
      conversationTitle: normalizeSpace(source.conversationTitle || ""),
      previewText: normalizeSpace(source.previewText || ""),
      attachmentSummary: normalizeSpace(source.attachmentSummary || ""),
      attachmentCount: Math.max(0, Math.floor(toNumber(source.attachmentCount, 0))),
      messageKind: messageKind,
      canJump: source.canJump !== false
    };
  }

  function normalizeMessage(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var id = Math.floor(toNumber(source.id, 0));
    if (id <= 0) return null;

    var profile = asObject(source.profile) || {};
    var sender = normalizeStudentNumber(source.studentNumber || source.senderStudentNumber || source.username);
    var text = toText(source.text);
    if (!text) text = "";
    var kind = normalizeSpace(source.kind || "text");
    if (kind !== "poll" && kind !== "attachment" && kind !== "voice") {
      kind = "text";
    }
    var attachments = (Array.isArray(source.attachments) ? source.attachments : [])
      .map(normalizeAttachment)
      .filter(Boolean);
    if (attachments.length && kind === "text") {
      kind = attachments[0].category === "voice" ? "voice" : "attachment";
    }
    var poll = normalizePoll(source.poll);

    return {
      id: id,
      conversationId: normalizeSpace(source.conversationId),
      studentNumber: sender,
      name: normalizeSpace(source.name)
        || (sender && sender === state.me.studentNumber
          ? (normalizeSpace(state.me.name) || "\u0634\u0645\u0627")
          : displayNameFallback(sender)),
      role: normalizeSpace(source.role) || "student",
      roleLabel: normalizeSpace(source.roleLabel) || "دانشجو",
      canModerateChat: !!source.canModerateChat,
      kind: kind,
      pollId: normalizeSpace(source.pollId || (poll && poll.id) || ""),
      poll: poll,
      text: text,
      ts: Math.floor(toNumber(source.ts, Math.floor(Date.now() / 1000))),
      editedAt: source.editedAt != null ? Math.floor(toNumber(source.editedAt, 0)) : null,
      replyTo: source.replyTo != null ? Math.floor(toNumber(source.replyTo, 0)) : null,
      pinned: !!source.pinned,
      reactions: asObject(source.reactions) || {},
      mentions: normalizeMentionUsers(source.mentions),
      attachments: attachments,
      meta: normalizeMessageMeta(source.meta),
      forwardedFrom: normalizeForwardedFrom(source.forwardedFrom),
      avatarUrl: normalizeAvatarUrl(source.avatarUrl || profile.avatarUrl || ""),
      about: normalizeSpace(source.about || profile.about || profile.bio || ""),
      delivery: normalizeSpace(source.delivery) || "sent",
      seenByCount: Math.max(0, Math.floor(toNumber(source.seenByCount, 0))),
      viewerImportantMentioned: !!source.viewerImportantMentioned
    };
  }

  function looksAutoConversationTitle(value) {
    var clean = normalizeSpace(value).toLowerCase();
    if (!clean) return false;
    if (/^(group|conversation|chat)[-_]?\d{3,}$/.test(clean)) return true;
    if (/^grp[-_]\d+$/.test(clean)) return true;
    if (/^conv[-_][0-9a-f]{8,}$/.test(clean)) return true;
    return false;
  }

  function conversationTitleFromSource(source, type, peer, conversationId) {
    var rawTitle = normalizeSpace(source && source.title);
    if (rawTitle) {
      if (!looksAutoConversationTitle(rawTitle)) {
        return rawTitle;
      }
      var hasHumanSignals = !!normalizeSpace(source && source.about)
        || !!normalizeSpace(source && source.subtitle)
        || !!asObject(source && source.lastMessage);
      if (hasHumanSignals) {
        return rawTitle;
      }
    }

    if (type === "direct") {
      var peerName = normalizeSpace(peer && peer.name);
      if (peerName) return peerName;
      var peerStudentNumber = normalizeStudentNumber(peer && peer.studentNumber);
      if (peerStudentNumber && canCurrentUserViewStudentNumbers()) {
        return "گفت‌وگو با " + peerStudentNumber;
      }
      return "گفت‌وگوی خصوصی";
    }

    if (type === "saved") {
      return "پیام‌های ذخیره‌شده";
    }

    if (type === "class-group") {
      return "گفت‌وگوی کلاس";
    }

    if (rawTitle && looksAutoConversationTitle(rawTitle)) {
      return "گروه یا کانال جدید";
    }

    var convId = normalizeSpace(conversationId);
    if (looksAutoConversationTitle(convId)) {
      return "گروه یا کانال جدید";
    }

    return rawTitle || "گروه";
  }

  function conversationSubtitleFromSource(source, type, peer, memberCount) {
    var rawSubtitle = normalizeSpace(source && source.subtitle);
    if (rawSubtitle) return rawSubtitle;

    if (type === "direct") {
      if (peer && peer.roleLabel) {
        return normalizeSpace(peer.roleLabel);
      }
      return "گفت‌وگوی خصوصی";
    }

    if (type === "saved") {
      return "فقط برای خودت";
    }

    if (type === "class-group") {
      return "گفت‌وگوی مشترک کلاسی";
    }

    var aboutText = normalizeSpace(source && source.about);
    if (aboutText) {
      return snippet(aboutText, 52);
    }

    var total = Math.max(0, Math.floor(toNumber(memberCount, 0)));
    if (total > 0) {
      return total.toLocaleString("fa-IR") + " عضو";
    }
    return "گروه";
  }

  function normalizeConversation(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var id = normalizeSpace(source.id);
    if (!id) return null;
    var type = normalizeSpace(source.type).toLowerCase();
    if (type !== "class-group" && type !== "direct" && type !== "group" && type !== "saved") {
      type = "";
    }
    if (!type && id.indexOf("dm:") === 0) {
      type = "direct";
    }
    if (!type && id.indexOf("saved:") === 0) {
      type = "saved";
    }
    if (type === "group" && id.indexOf("dm:") === 0) {
      type = "direct";
    }
    if (type === "group" && id.indexOf("saved:") === 0) {
      type = "saved";
    }
    if (!type) {
      type = "group";
    }

    var permissions = asObject(source.permissions) || {};
    var settings = asObject(source.settings) || {};
    var viewerState = asObject(source.viewerState) || {};
    var peer = normalizeUser(source.peer);
    var memberCount = Math.max(0, Math.floor(toNumber(source.memberCount, 0)));
    var conversationKind = normalizeSpace(source.conversationKind || settings.conversationKind || settings.kind).toLowerCase();
    if (conversationKind !== "channel") {
      conversationKind = "group";
    }
    var reactionMode = normalizeSpace(settings.reactionMode || source.reactionMode || "all").toLowerCase();
    if (reactionMode !== "quick" && reactionMode !== "none") {
      reactionMode = "all";
    }
    var visibility = normalizeSpace(settings.visibility || source.visibility || "private").toLowerCase();
    if (visibility !== "public") {
      visibility = "private";
    }
    var normalized = {
      id: id,
      type: type,
      kind: conversationKind,
      title: conversationTitleFromSource(source, type, peer, id),
      subtitle: conversationSubtitleFromSource(source, type, peer, memberCount),
      about: toText(source.about || ""),
      avatarUrl: normalizeAvatarUrl(source.avatarUrl),
      shareUrl: normalizeSpace(source.shareUrl),
      presence: normalizeConversationPresence(source.presence || {}, id),
      createdAt: Math.floor(toNumber(source.createdAt, 0)),
      updatedAt: Math.floor(toNumber(source.updatedAt, 0)),
      isMandatory: !!source.isMandatory,
      memberCount: memberCount,
      unreadCount: Math.max(0, Math.floor(toNumber(source.unreadCount, 0))),
      mentionCount: Math.max(0, Math.floor(toNumber(source.mentionCount, 0))),
      lastReadMessageId: Math.max(0, Math.floor(toNumber(source.lastReadMessageId, 0))),
      settings: {
        muted: !!settings.muted,
        mutedAt: settings.mutedAt != null ? Math.floor(toNumber(settings.mutedAt, 0)) : null,
        mutedBy: normalizeSpace(settings.mutedBy),
        conversationKind: conversationKind,
        memberPosting: settings.memberPosting !== false,
        reactionMode: reactionMode,
        visibility: visibility
      },
      viewerState: {
        pinned: !!viewerState.pinned,
        pinnedAt: viewerState.pinnedAt != null ? Math.floor(toNumber(viewerState.pinnedAt, 0)) : null,
        archived: !!viewerState.archived,
        archivedAt: viewerState.archivedAt != null ? Math.floor(toNumber(viewerState.archivedAt, 0)) : null,
        deleted: !!viewerState.deleted,
        deletedAt: viewerState.deletedAt != null ? Math.floor(toNumber(viewerState.deletedAt, 0)) : null,
        notificationsMuted: !!viewerState.notificationsMuted,
        notificationsMutedAt: viewerState.notificationsMutedAt != null ? Math.floor(toNumber(viewerState.notificationsMutedAt, 0)) : null
      },
      permissions: {
        canSend: permissions.canSend !== false,
        canManageConversation: !!permissions.canManageConversation,
        canPinMessages: !!permissions.canPinMessages,
        canPinConversation: !!permissions.canPinConversation,
        canMuteConversation: !!permissions.canMuteConversation,
        canArchiveConversation: !!permissions.canArchiveConversation,
        canLeaveConversation: !!permissions.canLeaveConversation,
        canClearHistory: !!permissions.canClearHistory,
        canDeleteConversation: !!permissions.canDeleteConversation,
        canMarkRead: permissions.canMarkRead !== false,
        canMarkUnread: permissions.canMarkUnread !== false,
        canCreateGroup: permissions.canCreateGroup !== false,
        canCreatePoll: !!permissions.canCreatePoll,
        canEditProfile: !!permissions.canEditProfile,
        canEditGroupType: !!permissions.canEditGroupType,
        canEditReactions: !!permissions.canEditReactions,
        canAddMembers: !!permissions.canAddMembers
      },
      lastMessage: normalizeMessage(source.lastMessage),
      pinnedMessage: normalizeMessage(source.pinnedMessage),
      searchText: normalizeSpace(source.searchText || source.searchIndex || ""),
      peer: peer,
      admins: Array.isArray(source.admins) ? source.admins.map(normalizeStudentNumber).filter(Boolean) : [],
      members: []
    };

    var members = Array.isArray(source.members) ? source.members : [];
    normalized.members = members.map(normalizeUser).filter(Boolean);
    return normalized;
  }

  function upsertConversation(conversation) {
    if (!conversation) return;
    var existing = state.conversationsById.get(conversation.id);
    if (existing) {
      var merged = Object.assign({}, existing, conversation);
      if (conversation.members && conversation.members.length) {
        merged.members = conversation.members;
      } else if (existing.members) {
        merged.members = existing.members;
      }
      state.conversationsById.set(conversation.id, merged);
      return;
    }
    state.conversationsById.set(conversation.id, conversation);
  }

  function mergeUserPresence(existingUser, incomingUser, conversationId) {
    var existing = asObject(existingUser) || null;
    var incoming = asObject(incomingUser) || null;
    if (!existing && !incoming) return null;
    var merged = Object.assign({}, existing || {}, incoming || {});
    merged.profile = Object.assign({}, existing && existing.profile ? existing.profile : {}, incoming && incoming.profile ? incoming.profile : {});
    merged.presence = normalizePresenceState(
      incoming && incoming.presence ? incoming.presence : (existing && existing.presence ? existing.presence : {}),
      conversationId || (merged.presence && merged.presence.typingConversationId) || ""
    );
    return merged;
  }

  function mergeConversationPresenceUpdate(update) {
    var source = asObject(update) || {};
    var id = normalizeSpace(source.id || source.conversationId);
    if (!id || !state.conversationsById.has(id)) return false;
    var current = state.conversationsById.get(id);
    var next = Object.assign({}, current);
    if (Object.prototype.hasOwnProperty.call(source, "presence")) {
      next.presence = normalizeConversationPresence(source.presence || {}, id);
    }
    if (source.peer) {
      next.peer = mergeUserPresence(current && current.peer, normalizeUser(source.peer), id);
    }
    if (Array.isArray(source.members) && Array.isArray(current && current.members)) {
      var memberMap = new Map();
      current.members.forEach(function (member) {
        if (member && member.studentNumber) {
          memberMap.set(member.studentNumber, member);
        }
      });
      next.members = source.members.map(function (member) {
        var normalized = normalizeUser(member);
        if (!normalized) return null;
        return mergeUserPresence(memberMap.get(normalized.studentNumber), normalized, id);
      }).filter(Boolean);
    }
    state.conversationsById.set(id, next);
    if (id === state.activeConversationId && current && current.peer && next.peer && current.avatarUrl !== next.avatarUrl) {
      updateThreadHead();
    }
    return true;
  }

  function applyPresenceBundle(raw, options) {
    var source = asObject(raw) || {};
    var updates = Array.isArray(source.conversations) ? source.conversations : [];
    if (!updates.length) return false;
    var changed = false;
    updates.forEach(function (entry) {
      if (mergeConversationPresenceUpdate(entry)) {
        changed = true;
      }
    });
    if (!changed) return false;
    state.conversations = state.conversations.map(function (conversation) {
      return state.conversationsById.get(conversation.id) || conversation;
    });
    var opts = asObject(options) || {};
    if (!opts.skipRender) {
      renderConversationList();
      updateThreadHead();
      updateInfoSheet();
    }
    return true;
  }

  function replaceConversations(list) {
    state.conversationsById.clear();
    (list || []).forEach(function (item) {
      upsertConversation(item);
    });
    state.conversations = sortConversations(Array.from(state.conversationsById.values()));
    scheduleFastChatCacheSave(180);
  }

  function rebuildConversationsFromMap() {
    state.conversations = sortConversations(Array.from(state.conversationsById.values()));
    scheduleFastChatCacheSave(180);
  }

  function activeConversation() {
    if (!state.activeConversationId) return null;
    return state.conversationsById.get(state.activeConversationId) || null;
  }

  function invalidateMessageListCache() {
    state.messageOrderDirty = true;
    state.messageOrderCache = [];
  }

  function messageList() {
    if (!state.messageOrderDirty) {
      return state.messageOrderCache.slice();
    }
    var list = Array.from(state.messages.values());
    list.sort(function (a, b) {
      return a.id - b.id;
    });
    state.messageOrderCache = list;
    state.messageOrderDirty = false;
    return list.slice();
  }

  function chatStorage() {
    try {
      return window.localStorage || null;
    } catch (_error) {
      return null;
    }
  }

  function chatCacheUserKey() {
    return normalizeStudentNumber(state.me && state.me.studentNumber);
  }

  function chatCacheKey() {
    var userKey = chatCacheUserKey();
    if (!userKey) return "";
    return [
      "dent1402-chat-fast",
      "v" + CHAT_FAST_CACHE_VERSION,
      encodeURIComponent(pageCohort || "main"),
      encodeURIComponent(userKey)
    ].join(":");
  }

  function chatDraftKey(conversationId) {
    var baseKey = chatCacheKey();
    var id = normalizeSpace(conversationId);
    if (!baseKey || !id) return "";
    return baseKey + ":draft:" + encodeURIComponent(id);
  }

  function compactCachedConversation(conversation) {
    if (!conversation) return null;
    var copy = Object.assign({}, conversation);
    if (Array.isArray(copy.members) && copy.members.length > 60) {
      copy.members = [];
    }
    return copy;
  }

  function compactCachedMessage(message) {
    if (!message || message.id <= 0) return null;
    return Object.assign({}, message);
  }

  function clearFastChatCacheSaveHandle() {
    if (state.cacheSaveTimer) {
      window.clearTimeout(state.cacheSaveTimer);
      state.cacheSaveTimer = null;
    }
    if (state.cacheSaveIdleId && typeof window.cancelIdleCallback === "function") {
      window.cancelIdleCallback(state.cacheSaveIdleId);
      state.cacheSaveIdleId = 0;
    }
  }

  function saveFastChatCacheNow() {
    clearFastChatCacheSaveHandle();
    if (!state.me.loggedIn) return;

    var storage = chatStorage();
    var key = chatCacheKey();
    if (!storage || !key) return;

    var activeId = normalizeSpace(state.activeConversationId);
    var cachedMessages = activeId
      ? messageList().filter(function (message) {
        return message && message.conversationId === activeId;
      }).slice(-CHAT_FAST_CACHE_MESSAGE_LIMIT).map(compactCachedMessage).filter(Boolean)
      : [];

    var payload = {
      version: CHAT_FAST_CACHE_VERSION,
      savedAt: Date.now(),
      cohort: pageCohort || "main",
      user: chatCacheUserKey(),
      activeConversationId: activeId,
      conversationListVersion: state.conversationListVersion || "",
      conversationListCategory: normalizeConversationListCategory(state.conversationListCategory),
      showArchivedConversations: state.showArchivedConversations === true,
      conversations: state.conversations
        .slice(0, CHAT_FAST_CACHE_CONVERSATION_LIMIT)
        .map(compactCachedConversation)
        .filter(Boolean),
      thread: {
        conversationId: activeId,
        messages: cachedMessages,
        lastMessageId: Math.max(0, Math.floor(toNumber(state.lastMessageId, 0))),
        oldestMessageId: Math.max(0, Math.floor(toNumber(state.oldestMessageId, 0))),
        hasMoreBefore: state.hasMoreBefore === true
      }
    };

    try {
      storage.setItem(key, JSON.stringify(payload));
    } catch (_error) {
      try {
        payload.thread.messages = payload.thread.messages.slice(-60);
        storage.setItem(key, JSON.stringify(payload));
      } catch (__error) {
        // Cache is best-effort; live sync remains the source of truth.
      }
    }
  }

  function scheduleFastChatCacheSave(delayMs) {
    if (!state.me.loggedIn) return;
    clearFastChatCacheSaveHandle();
    var delay = Math.max(0, Math.floor(toNumber(delayMs, 220)));
    state.cacheSaveTimer = window.setTimeout(function () {
      state.cacheSaveTimer = null;
      if (typeof window.requestIdleCallback === "function") {
        state.cacheSaveIdleId = window.requestIdleCallback(function () {
          state.cacheSaveIdleId = 0;
          saveFastChatCacheNow();
        }, { timeout: 900 });
        return;
      }
      saveFastChatCacheNow();
    }, delay);
  }

  function readFastChatCache() {
    var storage = chatStorage();
    var key = chatCacheKey();
    if (!storage || !key) return null;

    try {
      var payload = JSON.parse(storage.getItem(key) || "null");
      if (!payload || payload.version !== CHAT_FAST_CACHE_VERSION) return null;
      if (payload.cohort !== (pageCohort || "main")) return null;
      if (normalizeStudentNumber(payload.user) !== chatCacheUserKey()) return null;
      var savedAt = Math.max(0, Math.floor(toNumber(payload.savedAt, 0)));
      if (!savedAt || Date.now() - savedAt > CHAT_FAST_CACHE_TTL_MS) {
        storage.removeItem(key);
        return null;
      }
      return payload;
    } catch (_error) {
      return null;
    }
  }

  function hydrateFastChatCache() {
    if (!state.me.loggedIn || state.cacheHydrated) return false;

    var payload = readFastChatCache();
    var conversations = (Array.isArray(payload && payload.conversations) ? payload.conversations : [])
      .map(normalizeConversation)
      .filter(Boolean);
    if (!conversations.length) return false;

    replaceConversations(conversations);
    state.conversationListVersion = normalizeSpace(payload.conversationListVersion);
    state.conversationListCategory = normalizeConversationListCategory(payload.conversationListCategory);
    state.showArchivedConversations = payload.showArchivedConversations === true;

    var preferredConversationId = normalizeSpace(state.initialConversationId)
      || normalizeSpace(payload.activeConversationId);
    if (!preferredConversationId || !state.conversationsById.has(preferredConversationId)) {
      preferredConversationId = state.conversations[0] ? state.conversations[0].id : "";
    }
    state.activeConversationId = preferredConversationId;
    state.initialConversationId = "";

    var thread = asObject(payload.thread) || {};
    var cachedMessages = [];
    if (state.activeConversationId && normalizeSpace(thread.conversationId) === state.activeConversationId) {
      cachedMessages = (Array.isArray(thread.messages) ? thread.messages : [])
        .map(normalizeMessage)
        .filter(function (message) {
          return message && message.conversationId === state.activeConversationId;
        });
    }

    setThreadVisible(!!state.activeConversationId);
    if (state.activeConversationId && cachedMessages.length) {
      appendMessages(cachedMessages, {
        replaceAll: true,
        forceStick: true,
        smooth: false
      });
      state.hasMoreBefore = thread.hasMoreBefore === true;
      state.lastMessageId = Math.max(state.lastMessageId, Math.floor(toNumber(thread.lastMessageId, 0)));
      state.oldestMessageId = Math.max(0, Math.floor(toNumber(thread.oldestMessageId, state.oldestMessageId)));
    } else if (state.activeConversationId) {
      clearThreadState();
      setThreadVisible(true);
    } else {
      clearThreadState();
    }

    renderConversationList();
    updateConversationFilterTabs();
    updateThreadHead();
    updateComposerState();
    updatePinnedUi();
    var active = activeConversation();
    updateMuteUi(active && active.settings ? active.settings : { muted: false });
    updateInfoSheet();
    updateFabVisibility();
    updateMobileNav();
    restoreComposerDraftForConversation(state.activeConversationId, { preserveExisting: false });
    setConnectionState("sync", "در حال بروزرسانی...");
    state.cacheHydrated = true;
    state.cacheHydratedAt = Math.max(0, Math.floor(toNumber(payload.savedAt, 0)));
    return true;
  }

  function readComposerDraft(conversationId) {
    var storage = chatStorage();
    var key = chatDraftKey(conversationId);
    if (!storage || !key) return "";

    try {
      var payload = JSON.parse(storage.getItem(key) || "null");
      if (!payload) return "";
      var updatedAt = Math.max(0, Math.floor(toNumber(payload.updatedAt, 0)));
      if (!updatedAt || Date.now() - updatedAt > CHAT_DRAFT_TTL_MS) {
        storage.removeItem(key);
        return "";
      }
      return toText(payload.text).slice(0, MAX_MESSAGE_SIZE);
    } catch (_error) {
      return "";
    }
  }

  function saveComposerDraftNow(conversationId) {
    if (state.draftSaveTimer) {
      window.clearTimeout(state.draftSaveTimer);
      state.draftSaveTimer = null;
    }
    if (!state.me.loggedIn || !chatTextEl) return;

    var id = normalizeSpace(conversationId || state.activeConversationId);
    var storage = chatStorage();
    var key = chatDraftKey(id);
    if (!storage || !key) return;

    var text = toText(chatTextEl.value).slice(0, MAX_MESSAGE_SIZE);
    try {
      if (!normalizeSpace(text)) {
        storage.removeItem(key);
        return;
      }
      storage.setItem(key, JSON.stringify({
        text: text,
        updatedAt: Date.now()
      }));
    } catch (_error) {
      // Draft persistence is best-effort and should never block composing.
    }
  }

  function scheduleComposerDraftSave(conversationId) {
    if (!state.me.loggedIn) return;
    if (state.draftSaveTimer) {
      window.clearTimeout(state.draftSaveTimer);
    }
    var id = normalizeSpace(conversationId || state.activeConversationId);
    state.draftSaveTimer = window.setTimeout(function () {
      saveComposerDraftNow(id);
    }, CHAT_DRAFT_SAVE_DELAY_MS);
  }

  function clearComposerDraft(conversationId) {
    var storage = chatStorage();
    var key = chatDraftKey(conversationId || state.activeConversationId);
    if (!storage || !key) return;
    try {
      storage.removeItem(key);
    } catch (_error) {}
  }

  function restoreComposerDraftForConversation(conversationId, options) {
    if (!chatTextEl) return;
    var opts = asObject(options) || {};
    if (opts.preserveExisting !== false && normalizeSpace(chatTextEl.value)) {
      return;
    }
    chatTextEl.value = readComposerDraft(conversationId);
    autosizeComposer();
    syncComposerDraftState();
  }

  function clearThreadState() {
    state.messages.clear();
    invalidateMessageListCache();
    state.lastMessageId = 0;
    state.oldestMessageId = 0;
    state.hasMoreBefore = false;
    state.olderMessagesLoading = false;
    state.unreadDividerMessageId = 0;
    state.replyTargetId = null;
    state.messageSelectionMode = false;
    state.selectedMessageIds.clear();
    state.threadAutoStick = true;
    closeMentionSuggestions();
    pauseAllVoiceNotes();
    resetThreadSearchState({ keepPanel: false, keepNavigator: false });
    clearReplyTarget();
    if (messagesEl) messagesEl.innerHTML = "";
    showStreamState("empty", "گفت‌وگو خالی است", "برای شروع گفت‌وگو، یک پیام بفرست.");
    updateThreadSearchUi();
    updateMessageSelectionUi();
  }

  function setThreadVisible(visible) {
    if (!threadShell || !threadPlaceholder) return;
    threadShell.hidden = !visible;
    threadPlaceholder.hidden = !!visible;
  }

  function splitConversationBuckets(list) {
    var active = [];
    var archived = [];
    (Array.isArray(list) ? list : []).forEach(function (conversation) {
      if (!conversation) return;
      if (conversation.viewerState && conversation.viewerState.archived) {
        archived.push(conversation);
      } else {
        active.push(conversation);
      }
    });
    return { active: active, archived: archived };
  }

  function primaryMandatoryConversation() {
    var preferred = state.conversations.find(function (conversation) {
      return !!(conversation && conversation.isMandatory && !(conversation.viewerState && conversation.viewerState.archived));
    });
    if (preferred) return preferred;
    preferred = state.conversations.find(function (conversation) {
      return !!(conversation && conversation.isMandatory);
    });
    if (preferred) return preferred;
    return state.conversations[0] || null;
  }

  function updateConversationMeta() {
    if (!conversationMeta) return;
    var buckets = splitConversationBuckets(state.conversations);
    var total = state.conversations.length;
    var activeTotal = buckets.active.length;
    var archivedTotal = buckets.archived.length;
    var unread = buckets.active.reduce(function (count, item) {
      return count + Math.max(0, Math.floor(toNumber(item.unreadCount, 0)));
    }, 0);
    if (total <= 0) {
      conversationMeta.textContent = "هنوز گفت‌وگویی ثبت نشده است";
      return;
    }
    if (unread > 0) {
      conversationMeta.textContent = activeTotal.toLocaleString("fa-IR") + " گفت‌وگوی فعال • " + unread.toLocaleString("fa-IR") + " خوانده‌نشده";
      return;
    }
    if (archivedTotal > 0) {
      conversationMeta.textContent = activeTotal.toLocaleString("fa-IR") + " گفت‌وگوی فعال • " + archivedTotal.toLocaleString("fa-IR") + " بایگانی";
      return;
    }
    conversationMeta.textContent = activeTotal.toLocaleString("fa-IR") + " گفت‌وگوی فعال";
  }

  function updateThreadPlaceholderUi() {
    var buckets = splitConversationBuckets(state.conversations);
    var activeTotal = buckets.active.length;
    var unread = buckets.active.reduce(function (count, item) {
      return count + Math.max(0, Math.floor(toNumber(item && item.unreadCount, 0)));
    }, 0);
    var primaryConversation = primaryMandatoryConversation();

    if (placeholderActiveCount) {
      placeholderActiveCount.textContent = activeTotal.toLocaleString("fa-IR");
    }
    if (placeholderUnreadCount) {
      placeholderUnreadCount.textContent = unread.toLocaleString("fa-IR");
    }
    if (placeholderClassLabel) {
      placeholderClassLabel.textContent = primaryConversation
        ? snippet(toText(primaryConversation.title), 32)
        : "کلاس اجباری";
    }
    placeholderActionButtons.forEach(function (button) {
      if (button.getAttribute("data-placeholder-action") === "mandatory") {
        button.disabled = !primaryConversation;
      }
    });
  }

  function conversationBadgeText(conversation) {
    if (!conversation) return "";
    if (conversation.type === "class-group") return "کلاس";
    if (conversation.type === "direct") return "خصوصی";
    if (conversation.type === "saved") return "ذخیره";
    return "گروه";
  }

  function conversationTypeIconMarkup(conversation) {
    if (!conversation || conversation.type === "group") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8.5 11.5C10.1569 11.5 11.5 10.1569 11.5 8.5C11.5 6.84315 10.1569 5.5 8.5 5.5C6.84315 5.5 5.5 6.84315 5.5 8.5C5.5 10.1569 6.84315 11.5 8.5 11.5Z" stroke="currentColor" stroke-width="1.7"/><path d="M15.5 10.5C16.8807 10.5 18 9.38071 18 8C18 6.61929 16.8807 5.5 15.5 5.5C14.1193 5.5 13 6.61929 13 8C13 9.38071 14.1193 10.5 15.5 10.5Z" stroke="currentColor" stroke-width="1.7"/><path d="M4.5 18C4.5 15.7909 6.29086 14 8.5 14H9.5C11.7091 14 13.5 15.7909 13.5 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M13.5 17.5C13.5 15.8431 14.8431 14.5 16.5 14.5H17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    }
    if (conversation.type === "class-group") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.5 9.3L12 4.5L20.5 9.3L12 14.1L3.5 9.3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M7 11.2V16.2C7 17.7 9.24 19 12 19C14.76 19 17 17.7 17 16.2V11.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    }
    if (conversation.type === "saved") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 4.5H17A1.5 1.5 0 0 1 18.5 6V19L12 15.6L5.5 19V6A1.5 1.5 0 0 1 7 4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
    }
    return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12.5C14.0711 12.5 15.75 10.8211 15.75 8.75C15.75 6.67893 14.0711 5 12 5C9.92893 5 8.25 6.67893 8.25 8.75C8.25 10.8211 9.92893 12.5 12 12.5Z" stroke="currentColor" stroke-width="1.7"/><path d="M5.5 18.5C6.42 16.22 8.9 14.75 12 14.75C15.1 14.75 17.58 16.22 18.5 18.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
  }

  function conversationPreview(conversation) {
    var presenceText = conversationPresenceText(conversation);
    if (presenceText && conversation && conversation.presence && Array.isArray(conversation.presence.typingUsers) && conversation.presence.typingUsers.length) {
      return presenceText;
    }
    if (!conversation || !conversation.lastMessage) {
      if (conversation.type === "direct") return "شروع گفت‌وگو";
      if (conversation.type === "saved") return "یادداشت و فورواردهای شخصی‌ات را اینجا نگه دار";
      if (conversation.type === "class-group") return "گفت‌وگوی عمومی کلاس";
      var aboutText = normalizeSpace(conversation.about);
      if (aboutText) return snippet(aboutText, 96);
      var memberCount = Math.max(0, Math.floor(toNumber(conversation.memberCount, 0)));
      if (memberCount > 0) {
        return memberCount.toLocaleString("fa-IR") + " عضو • هنوز پیامی ثبت نشده";
      }
      return "هنوز پیامی ثبت نشده";
    }
    var last = conversation.lastMessage;
    var own = last.studentNumber === state.me.studentNumber && conversation.type !== "saved";
    var prefix = conversation.type === "saved" ? "" : (own ? "شما: " : (last.name ? last.name + ": " : ""));
    var deliveryPrefix = "";
    if (own) {
      deliveryPrefix = last.delivery === "seen" ? "✓✓ " : "✓ ";
    }
    return deliveryPrefix + prefix + snippet(messagePreviewText(last), 96);
  }

  function conversationListLabel(conversation) {
    if (!conversation) return "";
    if (conversation.lastMessage) return "";
    if (conversation.type === "class-group") return "کلاس";
    if (conversation.type === "direct") {
      return normalizeSpace(conversation.subtitle) || "خصوصی";
    }
    if (conversation.type === "saved") {
      return normalizeSpace(conversation.subtitle) || "شخصی";
    }
    var memberCount = Math.max(0, Math.floor(toNumber(conversation.memberCount, 0)));
    if (memberCount > 0) {
      return memberCount.toLocaleString("fa-IR") + " عضو";
    }
    return "گروه";
  }

  function renderConversationItem(conversation) {
    var node = document.createElement("article");
    node.className = "conversation-item";
    node.dataset.conversationId = conversation.id;
    node.dataset.conversationType = conversation.type || "group";
    if (conversation.id === state.activeConversationId) {
      node.classList.add("is-active");
    }
    if (conversation.unreadCount > 0) {
      node.classList.add("is-unread");
    }
    if ((conversation.viewerState && conversation.viewerState.pinned) || conversation.pinnedMessage) {
      node.classList.add("is-pinned");
    }
    if (conversation.viewerState && conversation.viewerState.archived) {
      node.classList.add("is-archived");
    }
    if (conversation.settings && conversation.settings.muted) {
      node.classList.add("is-muted");
    }
    if (state.selectedConversationIds.has(conversation.id)) {
      node.classList.add("is-selected");
    }

    var label = conversationListLabel(conversation);
    var lastTs = conversation.lastMessage ? conversation.lastMessage.ts : conversation.updatedAt;
    var timeLabel = lastTs ? formatTime(lastTs) : "";
    var unread = Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0)));
    var mentionCount = Math.max(0, Math.floor(toNumber(conversation.mentionCount, 0)));
    var flags = [];
    if (conversation.settings && conversation.settings.muted) {
      flags.push('<span class="conversation-flag conversation-flag--muted" title="بی‌صدا">•</span>');
    }
    if ((conversation.viewerState && conversation.viewerState.pinned) || conversation.pinnedMessage) {
      flags.push('<span class="conversation-flag conversation-flag--pinned" title="سنجاق‌شده">•</span>');
    }
    if (conversation.viewerState && conversation.viewerState.archived) {
      flags.push('<span class="conversation-flag conversation-flag--archived" title="بایگانی">•</span>');
    }

    node.innerHTML = [
      '<button type="button" class="conversation-item__main" data-open-conversation="1">',
      '  <span class="conversation-item__avatar" data-has-avatar="0">',
      '    <img alt="" hidden>',
      "    <span>" + escapeHtml(avatarLabel(conversation.title)) + "</span>",
      "  </span>",
      '  <span class="conversation-item__copy">',
      '    <span class="conversation-item__head">',
      '      <strong class="conversation-item__title" data-digit-locale="latin">' + escapeHtml(conversation.title) + "</strong>",
      "    </span>",
      '    <span class="conversation-item__preview-row">',
      (label ? '      <span class="conversation-item__subtitle" data-digit-locale="latin">' + escapeHtml(label) + "</span>" : ""),
      '      <span class="conversation-item__preview" data-digit-locale="latin">' + escapeHtml(conversationPreview(conversation)) + "</span>",
      "    </span>",
      "  </span>",
      '  <span class="conversation-item__meta">',
      '    <span class="conversation-item__time">' + escapeHtml(timeLabel) + "</span>",
      flags.length ? '    <span class="conversation-item__flags">' + flags.join("") + "</span>" : "",
      mentionCount > 0 ? '    <span class="conversation-item__mention" data-digit-locale="latin">@' + mentionCount.toLocaleString("fa-IR") + "</span>" : "",
      unread > 0 ? '    <span class="conversation-item__unread">' + unread.toLocaleString("fa-IR") + "</span>" : "",
      "  </span>",
      "</button>",
      '<span class="conversation-item__tools">',
      '  <label class="conversation-item__select"><input type="checkbox" data-conversation-select="1" value="' + escapeHtml(conversation.id) + '"' + (state.selectedConversationIds.has(conversation.id) ? " checked" : "") + '></label>',
      '  <button type="button" class="conversation-item__more" data-conversation-menu="1" aria-label="گزینه‌های گفتگو">&#8942;</button>',
      "</span>"
    ].join("");

    var avatar = node.querySelector(".conversation-item__avatar");
    var image = avatar ? avatar.querySelector("img") : null;
    var fallback = avatar ? avatar.querySelector("span") : null;
    renderAvatar(avatar, image, fallback, conversation.avatarUrl, conversation.title);
    setPresenceBadge(avatar, conversation);

    var openButton = node.querySelector("[data-open-conversation]");
    var selectBox = node.querySelector("[data-conversation-select]");
    var menuButton = node.querySelector("[data-conversation-menu]");

    if (openButton) {
      openButton.addEventListener("click", function () {
        if (state.listSelectionMode) {
          toggleConversationSelection(conversation.id);
          return;
        }
        openConversation(conversation.id, { forceFull: true, source: "click" });
      });

      openButton.addEventListener("contextmenu", function (event) {
        event.preventDefault();
        openListContextMenu(conversation, event.clientX, event.clientY);
      });

      var touchTimer = null;
      var startX = 0;
      var startY = 0;
      var cancelled = false;
      var clearTouchTimer = function () {
        if (!touchTimer) return;
        window.clearTimeout(touchTimer);
        touchTimer = null;
      };

      openButton.addEventListener("pointerdown", function (event) {
        if (event.pointerType !== "touch") return;
        startX = event.clientX;
        startY = event.clientY;
        cancelled = false;
        clearTouchTimer();
        touchTimer = window.setTimeout(function () {
          if (cancelled) return;
          openListContextMenu(conversation, event.clientX, event.clientY);
        }, 420);
      });
      openButton.addEventListener("pointermove", function (event) {
        if (event.pointerType !== "touch" || !touchTimer) return;
        var deltaX = Math.abs(event.clientX - startX);
        var deltaY = Math.abs(event.clientY - startY);
        if (deltaX > 9 || deltaY > 9) {
          cancelled = true;
          clearTouchTimer();
        }
      });
      ["pointerup", "pointercancel", "pointerleave"].forEach(function (eventName) {
        openButton.addEventListener(eventName, clearTouchTimer);
      });
    }

    if (selectBox) {
      selectBox.addEventListener("click", function (event) {
        event.stopPropagation();
      });
      selectBox.addEventListener("change", function () {
        if (!state.listSelectionMode && selectBox.checked) {
          state.listSelectionMode = true;
        }
        toggleConversationSelection(conversation.id, !!selectBox.checked);
      });
    }

    if (menuButton) {
      menuButton.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        var rect = menuButton.getBoundingClientRect();
        openListContextMenu(conversation, rect.left + (rect.width / 2), rect.bottom + 6);
      });
    }

    return node;
  }
  function filteredConversations() {
    var q = normalizeSpace(state.conversationFilter).toLowerCase();
    var qDigits = normalizeSpace(normalizeDigits(state.conversationFilter)).toLowerCase();
    var activeLoadedMessageText = "";
    if (q && state.activeConversationId && state.messages.size) {
      activeLoadedMessageText = messageList().map(function (message) {
        return messagePreviewText(message);
      }).join(" ");
    }
    return state.conversations.filter(function (conversation) {
      if (!conversationMatchesListCategory(conversation)) {
        return false;
      }
      if (!q) return true;
      var loadedMessageText = conversation.id === state.activeConversationId ? activeLoadedMessageText : "";
      var hay = [
        conversation.title,
        conversation.subtitle,
        conversationPreview(conversation),
        conversation.searchText,
        loadedMessageText,
        canCurrentUserViewStudentNumbers() && conversation.type === "direct" && conversation.peer ? conversation.peer.studentNumber : "",
        conversation.type === "saved" ? state.me.name : ""
      ].join(" ").toLowerCase();
      var hayDigits = normalizeDigits(hay).toLowerCase();
      return hay.indexOf(q) !== -1 || (qDigits && hayDigits.indexOf(qDigits) !== -1);
    });
  }

  function renderConversationList() {
    if (!conversationList) return;
    var list = filteredConversations();
    var buckets = splitConversationBuckets(list);
    var hasQuery = !!normalizeSpace(state.conversationFilter);
    var hasCategory = normalizeConversationListCategory(state.conversationListCategory) !== "all";
    var activeList = buckets.active;
    var archivedList = buckets.archived;
    var showArchived = state.showArchivedConversations || hasQuery || hasCategory;
    var hasAny = activeList.length > 0 || archivedList.length > 0;
    var listRenderKey = [
      state.activeConversationId,
      state.listSelectionMode ? "1" : "0",
      normalizeSpace(state.conversationFilter),
      normalizeConversationListCategory(state.conversationListCategory),
      showArchived ? "1" : "0",
      hasQuery ? [state.activeConversationId, state.messages.size, state.lastMessageId, state.oldestMessageId].join(":") : "",
      list.map(function (conversation) {
        var last = conversation && conversation.lastMessage ? conversation.lastMessage : null;
        return [
          conversation.id,
          conversation.title,
          conversation.subtitle,
          conversation.unreadCount,
          conversation.lastReadMessageId,
          conversation.updatedAt,
          last ? last.id : 0,
          last ? last.delivery : "",
          last ? snippet(messagePreviewText(last), 48) : "",
          conversation.viewerState && conversation.viewerState.pinned ? 1 : 0,
          conversation.viewerState && conversation.viewerState.archived ? 1 : 0,
          conversation.settings && conversation.settings.muted ? 1 : 0,
          state.selectedConversationIds.has(conversation.id) ? 1 : 0
        ].join(":");
      }).join("|")
    ].join("||");

    if (listRenderKey === state.conversationListRenderKey) {
      updateSelectionUi();
      updateConversationFilterTabs();
      updateConversationMeta();
      updateThreadPlaceholderUi();
      updateChatNavBadges();
      return;
    }

    state.conversationListRenderKey = listRenderKey;
    conversationList.innerHTML = "";
    setHidden(conversationEmpty, hasAny);

    if (conversationEmpty && !hasAny) {
      conversationEmpty.textContent = hasQuery
        ? "نتیجه‌ای برای جستجوی گفتگو پیدا نشد."
        : "هنوز گفتگویی پیدا نشد.";
    }

    var fragment = document.createDocumentFragment();
    if (activeList.length) {
      activeList.forEach(function (conversation) {
        fragment.appendChild(renderConversationItem(conversation));
      });
    }

    if (archivedList.length) {
      var archivedToggle = document.createElement("button");
      archivedToggle.type = "button";
      archivedToggle.className = "conversation-archive-toggle" + (showArchived ? " is-open" : "");
      archivedToggle.innerHTML = [
        '<span>گفتگوهای بایگانی‌شده</span>',
        '<strong>' + archivedList.length.toLocaleString("fa-IR") + "</strong>"
      ].join("");
      archivedToggle.addEventListener("click", function () {
        state.showArchivedConversations = !state.showArchivedConversations;
        renderConversationList();
        scheduleFastChatCacheSave(120);
      });
      fragment.appendChild(archivedToggle);

      if (showArchived) {
        archivedList.forEach(function (conversation) {
          fragment.appendChild(renderConversationItem(conversation));
        });
      }
    }

    conversationList.appendChild(fragment);

    if (state.modalOpen === "forward") {
      renderForwardList();
    }

    var validSelectedIds = new Set();
    state.selectedConversationIds.forEach(function (conversationId) {
      if (state.conversationsById.has(conversationId)) {
        validSelectedIds.add(conversationId);
      }
    });
    state.selectedConversationIds = validSelectedIds;
    updateSelectionUi();
    updateConversationFilterTabs();
    updateConversationMeta();
    updateThreadPlaceholderUi();
    updateChatNavBadges();
  }

  function updateThreadHead() {
    var conversation = activeConversation();
    if (threadUpdatingCount > 0) {
      if (conversationTitle) {
        conversationTitle.textContent = "درحال بروزرسانی";
        conversationTitle.dataset.updating = "1";
      }
      if (threadTitle) {
        threadTitle.textContent = "درحال بروزرسانی";
        threadTitle.dataset.updating = "1";
      }
      updateThreadSearchUi();
      return;
    }
    if (!conversation) {
      if (conversationTitle) conversationTitle.textContent = "گفتگوها";
      if (threadTitle) threadTitle.textContent = "گفت‌وگو";
      if (threadSubtitle) threadSubtitle.textContent = "یک گفت‌وگو را انتخاب کن";
      if (threadAvatar && threadAvatarImage && threadAvatarFallback) {
        renderAvatar(threadAvatar, threadAvatarImage, threadAvatarFallback, "", "?");
        setPresenceBadge(threadAvatar, null);
      }
      updateThreadSearchUi();
      return;
    }

    if (conversationTitle) conversationTitle.textContent = "گفتگوها";
    if (threadTitle) threadTitle.textContent = conversation.title;
    if (threadSubtitle) {
      var livePresenceText = conversationPresenceText(conversation);
      if (conversation.type === "direct" && conversation.peer) {
        var aboutText = normalizeSpace(conversation.peer.profile && conversation.peer.profile.about);
        threadSubtitle.textContent = livePresenceText || aboutText || conversation.subtitle || "گفت‌وگوی خصوصی";
      } else if (conversation.type === "saved") {
        threadSubtitle.textContent = conversation.subtitle || "یادداشت‌ها و فورواردهای شخصی خودت";
      } else {
        threadSubtitle.textContent = livePresenceText || conversation.subtitle || "";
      }
      threadSubtitle.classList.toggle("is-live-presence", !!livePresenceText);
    }
    if (threadAvatar && threadAvatarImage && threadAvatarFallback) {
      renderAvatar(threadAvatar, threadAvatarImage, threadAvatarFallback, conversation.avatarUrl, conversation.title);
      setPresenceBadge(threadAvatar, conversation);
    }
    updateThreadSearchUi();
  }

  function updatePinnedUi() {
    var conversation = activeConversation();
    if (!conversation || !pinnedWrap || !pinnedText) return;
    var pinned = conversation.pinnedMessage || null;

    if (!pinned) {
      pinnedWrap.hidden = true;
      if (unpinBtn) unpinBtn.hidden = true;
      return;
    }

    pinnedWrap.hidden = false;
    pinnedText.textContent = snippet(messagePreviewText(pinned), 120);
    if (pinnedActionLabel) pinnedActionLabel.textContent = "رفتن به پیام";
    if (unpinBtn) {
      unpinBtn.hidden = !(conversation.permissions && conversation.permissions.canPinMessages);
    }
  }

  function updateMuteUi(settings) {
    var conversation = activeConversation();
    if (!muteBadge) return;

    var muted = !!(settings && settings.muted);
    var canManage = !!(conversation && conversation.permissions && conversation.permissions.canMuteConversation);
    if (!muted) {
      muteBadge.hidden = true;
      muteBadge.textContent = "";
    } else {
      muteBadge.hidden = false;
      muteBadge.textContent = canManage
        ? "ارسال پیام در این گفت‌وگو بسته شده است. با ابزار مدیریت می‌توانی آن را باز کنی."
        : "ارسال پیام در این گفت‌وگو موقتاً بسته است.";
    }

    if (muteBtn) muteBtn.hidden = !(conversation && conversation.permissions && conversation.permissions.canMuteConversation && !muted);
    if (unmuteBtn) unmuteBtn.hidden = !(conversation && conversation.permissions && conversation.permissions.canMuteConversation && muted);
  }

  function updateComposerState() {
    var conversation = activeConversation();
    var canSend = !!(conversation && conversation.permissions && conversation.permissions.canSend);
    var muted = !!(conversation && conversation.settings && conversation.settings.muted);
    var hasUploadsInProgress = composerHasUploadingItems();
    var hasVoiceRecorder = !!state.voiceRecorder;
    var recordingVoice = !!(state.voiceRecorder && state.voiceRecorder.recording);
    var selectingMessages = !!state.messageSelectionMode;
    var shouldDisable = !conversation || !canSend || muted || selectingMessages;
    var canUseAttachmentTools = !!conversation && canSend && !muted && !selectingMessages;

    if (chatTextEl) {
      chatTextEl.disabled = shouldDisable;
      chatTextEl.placeholder = shouldDisable
        ? (selectingMessages
          ? "در حال انتخاب چند پیام هستی"
          : (conversation ? "ارسال پیام در این گفت‌وگو ممکن نیست" : "یک گفت‌وگو را انتخاب کن"))
        : "پیامت را بنویس...";
    }
    if (sendBtn) {
      sendBtn.disabled = shouldDisable || hasUploadsInProgress || recordingVoice;
    }
    if (attachBtn) {
      attachBtn.disabled = (conversation && !canUseAttachmentTools) || (canUseAttachmentTools && recordingVoice);
    }
    if (emojiBtn) {
      emojiBtn.disabled = shouldDisable;
    }
    if (voiceBtn) {
      voiceBtn.disabled = (conversation && !canUseAttachmentTools) || (canUseAttachmentTools && (hasUploadsInProgress || hasVoiceRecorder));
    }
    if (pollBtn) {
      var canCreatePoll = !!(conversation && conversation.permissions && conversation.permissions.canCreatePoll);
      pollBtn.hidden = !canCreatePoll;
      pollBtn.disabled = !canCreatePoll || !canUseAttachmentTools;
    }
    if (attachmentInput) {
      attachmentInput.disabled = (conversation && !canUseAttachmentTools) || (canUseAttachmentTools && recordingVoice);
    }

    if (!conversation) {
      setUploadSheetOpen(false); setEmojiPanelOpen(false);
      setComposerStatus("یک گفت‌وگو را برای شروع انتخاب کن.", "");
      return;
    }

    if (selectingMessages) {
      setUploadSheetOpen(false); setEmojiPanelOpen(false);
      setComposerStatus("حالت چندانتخابی پیام فعال است.", "");
      return;
    }

    if (!canSend) {
      setUploadSheetOpen(false); setEmojiPanelOpen(false);
      setComposerStatus("در حال حاضر اجازه ارسال پیام در این گفت‌وگو را ندارید.", "error");
      return;
    }

    if (muted) {
      setUploadSheetOpen(false); setEmojiPanelOpen(false);
      setComposerStatus("ارسال پیام در این گفت‌وگو بسته است.", "error");
      return;
    }

    if (hasUploadsInProgress) {
      setComposerStatus("در حال بارگذاری فایل...", "");
      return;
    }

    if (recordingVoice) {
      setComposerStatus("در حال ضبط پیام صوتی...", "");
      return;
    }

    if (state.connectionIssue) {
      setComposerStatus("اتصال ناپایدار است؛ در حال تلاش برای اتصال مجدد...", "error");
      return;
    }

    setComposerStatus("", "");
  }

  function reactionMap(message) {
    if (!message || !asObject(message.reactions)) return new Map();
    var map = new Map();
    Object.keys(message.reactions).forEach(function (emoji) {
      var users = Array.isArray(message.reactions[emoji]) ? message.reactions[emoji] : [];
      var unique = Array.from(new Set(users.map(function (item) {
        return normalizeSpace(item);
      }).filter(Boolean)));
      if (unique.length) {
        map.set(emoji, unique);
      }
    });
    return map;
  }

  function reactionEntries(message) {
    var map = reactionMap(message);
    var entries = [];
    map.forEach(function (users, emoji) {
      entries.push({
        emoji: emoji,
        users: users.slice(),
        count: users.length,
        own: users.indexOf(state.me.studentNumber) !== -1
      });
    });
    entries.sort(function (left, right) {
      if (right.count !== left.count) return right.count - left.count;
      return toText(left.emoji).localeCompare(toText(right.emoji), "en");
    });
    return entries;
  }

  function studentDisplayName(studentNumber) {
    var normalized = normalizeStudentNumber(studentNumber);
    if (!normalized) return "کاربر";
    if (normalized === normalizeStudentNumber(state.me.studentNumber)) {
      return normalizeSpace(state.me.name) || "شما";
    }

    var conversation = activeConversation();
    if (conversation && Array.isArray(conversation.members)) {
      for (var i = 0; i < conversation.members.length; i += 1) {
        var member = conversation.members[i];
        if (normalizeStudentNumber(member && member.studentNumber) === normalized) {
          return normalizeSpace(member.name) || (canCurrentUserViewStudentNumbers() ? normalized : "کاربر");
        }
      }
    }

    for (var m = 0; m < state.conversations.length; m += 1) {
      var candidate = state.conversations[m];
      if (!candidate || !Array.isArray(candidate.members)) continue;
      for (var c = 0; c < candidate.members.length; c += 1) {
        var user = candidate.members[c];
        if (normalizeStudentNumber(user && user.studentNumber) === normalized) {
          return normalizeSpace(user.name) || (canCurrentUserViewStudentNumbers() ? normalized : "کاربر");
        }
      }
    }

    var fromDirectory = state.directoryUsers.find(function (item) {
      return normalizeStudentNumber(item && item.studentNumber) === normalized;
    });
    if (fromDirectory) {
      return normalizeSpace(fromDirectory.name) || (canCurrentUserViewStudentNumbers() ? normalized : "کاربر");
    }

    var fromMessages = null;
    state.messages.forEach(function (item) {
      if (fromMessages) return;
      if (normalizeStudentNumber(item && item.studentNumber) === normalized) {
        fromMessages = item;
      }
    });
    if (fromMessages) {
      return normalizeSpace(fromMessages.name) || (canCurrentUserViewStudentNumbers() ? normalized : "کاربر");
    }

    return canCurrentUserViewStudentNumbers() ? normalized : "کاربر";
  }

  function reactionCountLabel(entry) {
    if (!entry) return "";
    return entry.count.toLocaleString("fa-IR") + " واکنش";
  }

  function findMessage(messageId) {
    return state.messages.get(Number(messageId)) || null;
  }

  function canManageMessage(message) {
    var conversation = activeConversation();
    if (!conversation || !message) return false;
    if (message.studentNumber && message.studentNumber === state.me.studentNumber) return true;
    return !!(conversation.permissions && conversation.permissions.canManageConversation);
  }

  function canPinMessage() {
    var conversation = activeConversation();
    return !!(conversation && conversation.permissions && conversation.permissions.canPinMessages);
  }

  function renderReplyPreview(replyToId) {
    var target = findMessage(replyToId);
    if (!target) return "";
    return (
      '<button type="button" class="reply-preview" data-reply-id="' + String(replyToId) + '">' +
      '<b data-digit-locale="latin">' + escapeHtml(target.name || "کاربر") + "</b>" +
      '<span data-digit-locale="latin">' + escapeHtml(snippet(messagePreviewText(target), 90)) + "</span>" +
      "</button>"
    );
  }

  function forwardedOriginHeadline(meta) {
    if (!meta) return "پیام فورواردشده";
    if (meta.senderName) {
      return "فوروارد از " + meta.senderName;
    }
    return "پیام فورواردشده";
  }

  function forwardedOriginPreview(meta) {
    if (!meta) return "";
    return normalizeSpace(meta.previewText || meta.attachmentSummary || "باز کردن پیام مبدا");
  }

  function forwardedOriginMeta(meta) {
    if (!meta) return "";
    var parts = [];
    if (meta.conversationTitle) {
      parts.push(meta.conversationTitle);
    }
    if (meta.attachmentSummary && meta.attachmentSummary !== meta.previewText) {
      parts.push(meta.attachmentSummary);
    }
    return parts.join(" • ");
  }

  function renderForwardHeader(message) {
    var meta = message && message.forwardedFrom;
    if (!meta) return "";
    var attrs = meta.canJump
      ? (' data-forward-origin="1" data-origin-conversation="' + escapeHtml(meta.conversationId) + '" data-origin-message="' + escapeHtml(String(meta.messageId)) + '"')
      : "";
    return [
      '<button type="button" class="msg-forwarded"' + attrs + (meta.canJump ? "" : ' disabled aria-disabled="true"') + '>',
      '  <span class="msg-forwarded__kicker">' + escapeHtml(forwardedOriginHeadline(meta)) + "</span>",
      '  <strong class="msg-forwarded__preview">' + escapeHtml(forwardedOriginPreview(meta)) + "</strong>",
      forwardedOriginMeta(meta) ? ('  <small class="msg-forwarded__meta">' + escapeHtml(forwardedOriginMeta(meta)) + "</small>") : "",
      "</button>"
    ].join("");
  }

  async function openForwardOrigin(message) {
    var meta = message && message.forwardedFrom;
    if (!meta || !meta.conversationId || !meta.messageId) {
      showToast("مرجع پیام فورواردی در دسترس نیست.");
      return;
    }

    try {
      if (normalizeSpace(state.activeConversationId) !== normalizeSpace(meta.conversationId)) {
        await openConversation(meta.conversationId, {
          forceFull: false,
          source: "forward-origin",
          silent: true
        });
      }
      await loadThreadContext(meta.messageId, {
        behavior: "smooth",
        block: "center",
        durationMs: 2200
      });
    } catch (error) {
      showToast((error && error.message) || "باز کردن پیام مبدا انجام نشد.");
    }
  }

  function renderReactions(message) {
    var entries = reactionEntries(message);
    if (!entries.length) return "";
    return '<div class="msg-reactions">' +
      entries.map(function (entry) {
        var title = entry.emoji + " • " + reactionCountLabel(entry);
        return (
          '<button type="button" class="msg-reaction' + (entry.own ? " is-own" : "") + '" data-reaction-emoji="' + escapeHtml(entry.emoji) + '" title="' + escapeHtml(title) + '" aria-label="' + escapeHtml(title) + '">' +
          "<span>" + escapeHtml(entry.emoji) + "</span>" +
          '<span class="msg-reaction__count">' + entry.count.toLocaleString("fa-IR") + "</span>" +
          "</button>"
        );
      }).join("") +
      "</div>";
  }

  function bindReactionButton(button, message) {
    if (!button || !message) return;
    var emoji = normalizeSpace(button.getAttribute("data-reaction-emoji"));
    if (!emoji) return;

    var holdTimer = null;
    var holdOpened = false;
    var lastDetailsOpenAt = 0;
    var startX = 0;
    var startY = 0;

    function clearHoldTimer() {
      if (holdTimer) {
        window.clearTimeout(holdTimer);
        holdTimer = null;
      }
    }

    function openDetails(event) {
      var now = Date.now();
      if ((now - lastDetailsOpenAt) < 220) return;
      lastDetailsOpenAt = now;
      holdOpened = true;
      clearHoldTimer();
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      openReactionDetailsModal(message, emoji);
    }

    button.addEventListener("contextmenu", function (event) {
      openDetails(event);
    });

    button.addEventListener("pointerdown", function (event) {
      if (event.button != null && event.button !== 0) return;
      startX = event.clientX;
      startY = event.clientY;
      holdOpened = false;
      clearHoldTimer();
      holdTimer = window.setTimeout(function () {
        openDetails(event);
      }, event.pointerType === "mouse" ? 420 : 360);
    });

    button.addEventListener("pointermove", function (event) {
      if (!holdTimer) return;
      var deltaX = Math.abs(event.clientX - startX);
      var deltaY = Math.abs(event.clientY - startY);
      if (deltaX > 8 || deltaY > 8) {
        clearHoldTimer();
      }
    });

    ["pointerup", "pointercancel", "pointerleave"].forEach(function (eventName) {
      button.addEventListener(eventName, clearHoldTimer);
    });

    button.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();
      if (holdOpened) {
        holdOpened = false;
        return;
      }
      toggleReaction(message, emoji);
    });

    button.addEventListener("keydown", function (event) {
      if (event.key !== "Enter" && event.key !== " ") return;
      event.preventDefault();
      event.stopPropagation();
      toggleReaction(message, emoji);
    });
  }

  function attachmentMetaText(attachment) {
    var parts = [attachmentCategoryLabel(attachment.category)];
    if ((attachment.category === "audio" || attachment.category === "voice") && attachment.durationSeconds > 0) {
      parts.push(formatDuration(attachment.durationSeconds));
    }
    if (attachment.sizeBytes > 0) {
      parts.push(formatFileSize(attachment.sizeBytes));
    }
    return parts.join(" • ");
  }

  function isVisualAttachmentCategory(category) {
    return category === "image" || category === "video";
  }

  function isVisualAttachment(attachment) {
    return !!attachment && isVisualAttachmentCategory(normalizeAttachmentCategory(attachment.category));
  }

  function attachmentPreviewPayload(attachment, options) {
    if (!attachment || !isVisualAttachment(attachment)) return null;
    var opts = asObject(options) || {};
    var previewUrl = attachment.previewUrl || attachment.url;
    if (!previewUrl || (!attachment.available && !attachment.hasPreview)) return null;
    var kind = attachment.category === "video" ? "video" : "image";
    var src = kind === "video" && attachment.available && attachment.url
      ? attachment.url
      : (attachment.url || previewUrl);
    if (!src) return null;
    return {
      kind: kind,
      src: src,
      previewUrl: previewUrl,
      poster: kind === "video" ? previewUrl : "",
      caption: normalizeSpace(opts.caption || attachment.name || ""),
      group: normalizeSpace(opts.group || ""),
      downloadUrl: normalizeSpace(attachment.downloadUrl || attachment.url || ""),
      messageId: opts.messageId ? String(opts.messageId) : "",
      durationSeconds: Math.max(0, Math.floor(toNumber(attachment.durationSeconds, 0)))
    };
  }

  function renderAttachmentMediaButton(attachment, options) {
    var payload = attachmentPreviewPayload(attachment, options);
    if (!payload) return "";
    var opts = asObject(options) || {};
    var className = normalizeSpace(opts.className || "msg-attachment__media msg-attachment__media-btn");
    var badgeText = normalizeSpace(opts.badgeText);
    if (!badgeText && payload.kind === "video") {
      badgeText = payload.durationSeconds > 0 ? formatDuration(payload.durationSeconds) : "ویدیو";
    }
    return [
      '<button type="button" class="' + escapeHtml(className) + '" data-media-kind="' + escapeHtml(payload.kind) + '" data-media-src="' + escapeHtml(payload.src) + '"' + (payload.poster ? ' data-media-poster="' + escapeHtml(payload.poster) + '"' : "") + ' data-media-caption="' + escapeHtml(payload.caption || "رسانه") + '"' + (payload.group ? ' data-media-group="' + escapeHtml(payload.group) + '"' : "") + (payload.downloadUrl ? ' data-media-download="' + escapeHtml(payload.downloadUrl) + '"' : "") + (payload.messageId ? ' data-media-message-id="' + escapeHtml(payload.messageId) + '"' : "") + '>',
      payload.kind === "video"
        ? (
          attachment.available && attachment.url
            ? '<video playsinline muted preload="metadata" src="' + escapeHtml(payload.src) + '" poster="' + escapeHtml(payload.poster) + '"></video>'
            : '<img src="' + escapeHtml(payload.previewUrl) + '" alt="' + escapeHtml(payload.caption || "رسانه") + '" loading="lazy">'
        )
        : '<img src="' + escapeHtml(payload.previewUrl) + '" alt="' + escapeHtml(payload.caption || "رسانه") + '" loading="lazy">',
      payload.kind === "video" ? '<span class="msg-media-preview__play" aria-hidden="true"></span>' : "",
      badgeText ? '<span class="msg-media-preview__badge">' + escapeHtml(badgeText) + "</span>" : "",
      "</button>"
    ].join("");
  }

  function renderAttachmentMedia(attachment, options) {
    var mediaButton = renderAttachmentMediaButton(attachment, Object.assign({}, asObject(options) || {}, {
      className: "msg-attachment__media msg-attachment__media-btn"
    }));
    if (mediaButton) return mediaButton;

    if ((attachment.category === "audio" || attachment.category === "voice") && attachment.available && attachment.url) {
      return '<audio class="msg-attachment__audio" controls preload="metadata" src="' + escapeHtml(attachment.url) + '"></audio>';
    }

    return "";
  }

  function renderVoiceAttachment(attachment) {
    var duration = Math.max(0, Math.floor(toNumber(attachment && attachment.durationSeconds, 0)));
    var bars = renderWaveBarsMarkup(
      seededWaveformSamples((attachment && attachment.id) || (attachment && attachment.url) || "voice", VOICE_WAVE_BAR_COUNT),
      "msg-voice-note__bar"
    );
    var downloadUrl = attachment && (attachment.downloadUrl || attachment.url) ? (attachment.downloadUrl || attachment.url) : "";
    return [
      '<article class="msg-voice-note' + (attachment && attachment.available ? "" : " is-unavailable") + '" data-voice-id="' + escapeHtml(attachment && attachment.id) + '" data-voice-duration="' + duration + '">',
      attachment && attachment.available && attachment.url ? ('  <audio class="msg-voice-note__audio" preload="metadata" src="' + escapeHtml(attachment.url) + '"></audio>') : "",
      '  <button type="button" class="msg-voice-note__play"' + (attachment && attachment.available ? "" : ' disabled aria-disabled="true"') + ' aria-label="پخش پیام صوتی">',
      '    <span class="msg-voice-note__play-icon msg-voice-note__play-icon--play" aria-hidden="true"></span>',
      "  </button>",
      '  <div class="msg-voice-note__body">',
      '    <div class="msg-voice-note__wave">' + bars + "</div>",
      '    <div class="msg-voice-note__meta">',
      '      <span class="msg-voice-note__time" data-digit-locale="latin">' + escapeHtml(formatDuration(duration)) + "</span>",
      '      <button type="button" class="msg-voice-note__speed"' + (attachment && attachment.available ? "" : ' disabled aria-disabled="true"') + '>' + escapeHtml(voiceSpeedLabel(1)) + "</button>",
      downloadUrl ? ('      <a class="msg-voice-note__download" href="' + escapeHtml(downloadUrl) + '" target="_blank" rel="noopener">دانلود</a>') : "",
      attachment && !attachment.available ? '      <span class="msg-voice-note__state">فایل صوتی اصلی فعلاً در دسترس نیست.</span>' : "",
      "    </div>",
      "  </div>",
      "</article>"
    ].join("");
  }

  function messageLinks(text) {
    var raw = toText(text);
    var matches = raw.match(/https?:\/\/[^\s<>"']+/ig) || [];
    return Array.from(new Set(matches)).slice(0, 3);
  }

  function internalRoutePreviewPayload(url) {
    var href = "";
    try {
      var resolved = new URL(url, window.location.origin);
      if (resolved.origin !== window.location.origin) {
        return null;
      }
      href = parseInternalRouteHref(resolved.pathname + resolved.search + resolved.hash);
    } catch (_error) {
      href = parseInternalRouteHref(url);
    }
    if (!href) return null;

    var section = routeCardSectionFromHref(href);
    if (!section) return null;

    var description = "";
    try {
      var hrefUrl = new URL(href, window.location.origin);
      var extraPath = decodeURIComponent(
        (hrefUrl.pathname || "").replace(/^\/(?:notes|exams|forms)\/?/i, "")
      ).replace(/[-_]+/g, " ").trim();
      if (extraPath) {
        description = snippet(extraPath, 90);
      } else if (hrefUrl.search) {
        description = snippet(hrefUrl.search.replace(/[?&=]+/g, " ").trim(), 90);
      }
    } catch (_error) {}

    return {
      section: section,
      href: href,
      title: routeCardDefaultTitle(section),
      description: description,
      ctaLabel: "باز کردن",
      badge: routeCardSectionLabel(section)
    };
  }

  function renderRouteLinkCard(routeLink, options) {
    var source = asObject(routeLink) || {};
    var href = parseInternalRouteHref(source.href || "");
    var section = normalizeSpace(source.section || routeCardSectionFromHref(href));
    if (!href || !section) return "";
    var safeHref = scopedInternalHref(href);
    var title = normalizeSpace(source.title) || routeCardDefaultTitle(section);
    var description = normalizeSpace(source.description || "");
    var badge = normalizeSpace(source.badge || "") || routeCardSectionLabel(section);
    var ctaLabel = normalizeSpace(source.ctaLabel || "") || "باز کردن";
    var opts = asObject(options) || {};
    var className = "msg-card msg-card--route" + (opts.previewOnly ? " is-preview" : "");
    return [
      '<article class="' + className + '">',
      '  <div class="msg-card__head">',
      '    <span class="msg-card__badge">' + escapeHtml(badge) + "</span>",
      '    <span class="msg-card__meta">' + escapeHtml(section === "notes" ? "مسیر آموزشی" : "لینک داخلی") + "</span>",
      "  </div>",
      '  <strong class="msg-card__title">' + escapeHtml(title) + "</strong>",
      description ? ('  <p class="msg-card__text">' + escapeHtml(description) + "</p>") : "",
      safeHref ? ('  <a class="msg-card__cta" href="' + escapeHtml(safeHref) + '" data-bypass-external-warning="true">' + escapeHtml(ctaLabel) + "</a>") : "",
      "</article>"
    ].join("");
  }

  function reminderToneLabel(tone) {
    switch (normalizeSpace(tone)) {
      case "urgent":
        return "فوری";
      case "important":
        return "مهم";
      default:
        return "عادی";
    }
  }

  function renderTaskReminderCard(taskReminder) {
    var source = asObject(taskReminder) || {};
    var title = normalizeSpace(source.title);
    if (!title) return "";
    var dueAt = source.dueAt != null ? Math.max(0, Math.floor(toNumber(source.dueAt, 0))) : 0;
    var categoryLabel = normalizeSpace(source.categoryLabel || "") || taskReminderCategoryLabel(source.category || "deadline");
    var tone = normalizeSpace(source.tone || "normal");
    if (tone !== "urgent" && tone !== "important") {
      tone = "normal";
    }
    var metaParts = [categoryLabel];
    if (dueAt > 0) {
      metaParts.push("مهلت: " + formatDateTime(dueAt));
    }
    if (tone !== "normal") {
      metaParts.push(reminderToneLabel(tone));
    }
    var safeHref = scopedInternalHref(source.ctaHref || "");
    var ctaLabel = safeHref ? (normalizeSpace(source.ctaLabel || "") || "باز کردن") : "";
    return [
      '<article class="msg-card msg-card--task is-tone-' + escapeHtml(tone) + '">',
      '  <div class="msg-card__head">',
      '    <span class="msg-card__badge">' + escapeHtml(categoryLabel) + "</span>",
      '    <span class="msg-card__meta" data-digit-locale="latin">' + escapeHtml(metaParts.join(" • ")) + "</span>",
      "  </div>",
      '  <strong class="msg-card__title">' + escapeHtml(title) + "</strong>",
      normalizeSpace(source.details) ? ('  <p class="msg-card__text">' + escapeHtml(normalizeSpace(source.details)) + "</p>") : "",
      safeHref ? ('  <a class="msg-card__cta" href="' + escapeHtml(safeHref) + '" data-bypass-external-warning="true">' + escapeHtml(ctaLabel) + "</a>") : "",
      "</article>"
    ].join("");
  }

  function renderMessageMetaCard(message) {
    if (!message || !message.meta) return "";
    if (message.meta.type === "route-link" && message.meta.routeLink) {
      return renderRouteLinkCard(message.meta.routeLink);
    }
    if (message.meta.type === "task-reminder" && message.meta.taskReminder) {
      return renderTaskReminderCard(message.meta.taskReminder);
    }
    return "";
  }

  function renderLinkPreviews(message) {
    var urls = messageLinks(message && message.text);
    if (!urls.length) return "";
    var primaryMetaHref = parseInternalRouteHref(
      message && message.meta && message.meta.type === "route-link" && message.meta.routeLink
        ? message.meta.routeLink.href
        : ""
    );
    return '<div class="msg-link-previews">' + urls.map(function (url) {
      var internalPreview = internalRoutePreviewPayload(url);
      if (internalPreview) {
        if (primaryMetaHref && internalPreview.href === primaryMetaHref) {
          return "";
        }
        return renderRouteLinkCard(internalPreview, { previewOnly: true });
      }
      var host = "";
      try {
        host = new URL(url).host.replace(/^www\./i, "");
      } catch (error) {
        host = url;
      }
      return [
        '<a class="msg-link-preview" href="' + escapeHtml(url) + '" target="_blank" rel="noopener" data-bypass-external-warning="true">',
        '  <span class="msg-link-preview__rail"></span>',
        '  <span class="msg-link-preview__copy">',
        '    <strong>' + escapeHtml(host || "لینک") + '</strong>',
        '    <small>' + escapeHtml(url) + '</small>',
        '  </span>',
        '</a>'
      ].join("");
    }).join("") + "</div>";
  }

  function mentionDisplayLabel(user, fallbackStudentNumber) {
    var source = asObject(user) || {};
    var name = normalizeSpace(source.name);
    var studentNumber = normalizeStudentNumber(source.studentNumber || fallbackStudentNumber);
    if (name) {
      return "@" + name;
    }
    if (studentNumber) {
      return "@" + studentNumber;
    }
    return "@کاربر";
  }

  function renderMessageText(message) {
    var text = meaningfulMessageText(message);
    if (!text) return "";
    var mentionMap = new Map();
    (Array.isArray(message && message.mentions) ? message.mentions : []).forEach(function (user) {
      var normalized = normalizeStudentNumber(user && user.studentNumber);
      if (!normalized || mentionMap.has(normalized)) return;
      mentionMap.set(normalized, user);
    });

    var html = "";
    var lastIndex = 0;
    var regex = /(^|[\s(\[<{])@([A-Za-z0-9][A-Za-z0-9._-]{1,31})/gm;
    var match = null;
    while ((match = regex.exec(text))) {
      var prefix = match[1] || "";
      var token = match[2] || "";
      var start = match.index + prefix.length;
      var end = start + 1 + token.length;
      html += escapeHtml(text.slice(lastIndex, start));
      var studentNumber = normalizeStudentNumber(token);
      var user = studentNumber ? mentionMap.get(studentNumber) : null;
      if (user) {
        html += '<span class="msg-mention" data-mention-student="' + escapeHtml(studentNumber) + '">' + escapeHtml(mentionDisplayLabel(user, studentNumber)) + "</span>";
      } else {
        html += escapeHtml(text.slice(start, end));
      }
      lastIndex = end;
    }
    html += escapeHtml(text.slice(lastIndex));
    return html;
  }

  function pollDraftSelection(poll) {
    if (!poll || !poll.id) return [];
    var stored = state.pollDraftSelections.get(poll.id);
    if (!Array.isArray(stored)) {
      stored = Array.isArray(poll.viewer && poll.viewer.selectedOptionIds) ? poll.viewer.selectedOptionIds.slice() : [];
      state.pollDraftSelections.set(poll.id, stored);
    }
    return stored.slice();
  }

  function setPollDraftSelection(pollId, optionIds) {
    var id = normalizeSpace(pollId);
    if (!id) return;
    state.pollDraftSelections.set(id, Array.from(new Set((Array.isArray(optionIds) ? optionIds : []).map(function (item) {
      return normalizeSpace(item);
    }).filter(Boolean))));
  }

  function resetPollDraftSelection(poll) {
    if (!poll || !poll.id) return;
    setPollDraftSelection(poll.id, Array.isArray(poll.viewer && poll.viewer.selectedOptionIds) ? poll.viewer.selectedOptionIds : []);
  }

  function pollStatusLabel(poll) {
    if (!poll) return "";
    if (poll.status === "closed") return "بسته";
    if (poll.status === "scheduled") return "زمان‌بندی‌شده";
    return "باز";
  }

  function pollSummaryText(poll) {
    if (!poll) return "";
    var parts = [];
    parts.push(poll.settings && poll.settings.multipleChoice
      ? ("چندگزینه‌ای" + (poll.settings.maxChoices > 1 ? (" تا " + poll.settings.maxChoices.toLocaleString("fa-IR") + " انتخاب") : ""))
      : "تک‌گزینه‌ای");
    if (poll.settings && poll.settings.anonymous) {
      parts.push("مخفی");
    }
    if (poll.endAt) {
      parts.push("مهلت: " + formatDateTime(poll.endAt));
    }
    return parts.join(" • ");
  }

  function pollResultsSummary(poll) {
    if (!poll || !poll.results) return "";
    if (!poll.results.visible) {
      return normalizeSpace(poll.results.hiddenReason) || "نتایج هنوز نمایش داده نمی‌شود.";
    }
    var voters = poll.results.totalVoters == null ? null : Math.max(0, Math.floor(toNumber(poll.results.totalVoters, 0)));
    var votes = poll.results.totalVotes == null ? null : Math.max(0, Math.floor(toNumber(poll.results.totalVotes, 0)));
    var parts = [];
    if (voters != null) {
      parts.push(voters.toLocaleString("fa-IR") + " رأی‌دهنده");
    }
    if (votes != null && votes !== voters) {
      parts.push(votes.toLocaleString("fa-IR") + " رأی");
    }
    return parts.join(" • ");
  }

  function renderPollCard(message) {
    var poll = message && message.poll;
    if (!poll) return "";
    var canVote = !!(poll.viewer && poll.viewer.canVote);
    var multipleChoice = !!(poll.settings && poll.settings.multipleChoice);
    var selectedOptionIds = multipleChoice && canVote ? pollDraftSelection(poll) : (Array.isArray(poll.viewer && poll.viewer.selectedOptionIds) ? poll.viewer.selectedOptionIds.slice() : []);
    var selectedLookup = new Set(selectedOptionIds);
    var voteSummary = pollResultsSummary(poll);
    var canSubmitMulti = multipleChoice && canVote;
    var submitDisabled = !canSubmitMulti || !selectedOptionIds.length;

    return [
      '<section class="msg-poll" data-poll-id="' + escapeHtml(poll.id) + '" data-message-id="' + escapeHtml(String(message.id)) + '">',
      '  <div class="msg-poll__head">',
      '    <strong class="msg-poll__question">' + escapeHtml(poll.question || "نظرسنجی") + "</strong>",
      '    <span class="msg-poll__status">' + escapeHtml(pollStatusLabel(poll)) + "</span>",
      "  </div>",
      '  <div class="msg-poll__meta" data-digit-locale="latin">' + escapeHtml(pollSummaryText(poll)) + "</div>",
      '  <div class="msg-poll__options">' + poll.options.map(function (option) {
        var selected = selectedLookup.has(option.id);
        var percent = option.percent == null ? 0 : clamp(toNumber(option.percent, 0), 0, 100);
        var voteCount = option.voteCount == null ? "" : option.voteCount.toLocaleString("fa-IR") + " رأی";
        return [
          '<button type="button" class="msg-poll__option' + (selected ? " is-selected" : "") + (!canVote ? " is-readonly" : "") + '" data-poll-option="' + escapeHtml(option.id) + '"' + (canVote ? "" : ' disabled aria-disabled="true"') + '>',
          poll.results && poll.results.visible ? ('  <span class="msg-poll__option-fill" style="--poll-fill:' + escapeHtml(String(percent)) + '%"></span>') : "",
          '  <span class="msg-poll__option-copy">',
          '    <strong>' + escapeHtml(option.text || "گزینه") + "</strong>",
          (poll.results && poll.results.visible) ? ('    <small data-digit-locale="latin">' + escapeHtml(voteCount + (voteCount && option.percent != null ? " • " : "") + (option.percent != null ? option.percent.toLocaleString("fa-IR") + "%" : "")) + "</small>") : "",
          "  </span>",
          '  <span class="msg-poll__option-check" aria-hidden="true">' + (selected ? "✓" : "") + "</span>",
          "</button>"
        ].join("");
      }).join("") + "</div>",
      voteSummary ? ('  <div class="msg-poll__summary" data-digit-locale="latin">' + escapeHtml(voteSummary) + "</div>") : "",
      '  <div class="msg-poll__actions">',
      canSubmitMulti ? ('    <button type="button" class="msg-poll__action msg-poll__action--primary" data-poll-submit' + (submitDisabled ? ' disabled aria-disabled="true"' : "") + '>ثبت رأی</button>') : "",
      canSubmitMulti ? '    <button type="button" class="msg-poll__action" data-poll-reset>بازگردانی</button>' : "",
      poll.permissions && poll.permissions.canClose ? '    <button type="button" class="msg-poll__action" data-poll-close>بستن نظرسنجی</button>' : "",
      poll.permissions && poll.permissions.canReopen ? '    <button type="button" class="msg-poll__action" data-poll-reopen>بازگشایی</button>' : "",
      "  </div>",
      "</section>"
    ].join("");
  }

  function pollOptionRowMarkup(value) {
    return [
      '<div class="poll-option-row" data-poll-option-row>',
      '  <input type="text" maxlength="120" placeholder="گزینه" data-poll-option-input value="' + escapeHtml(value || "") + '">',
      '  <button type="button" class="poll-option-remove" data-poll-option-remove aria-label="حذف گزینه">',
      '    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 7L17 17" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M17 7L7 17" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>',
      '  </button>',
      '</div>'
    ].join("");
  }

  function pollOptionRows() {
    return pollOptionsList ? Array.from(pollOptionsList.querySelectorAll("[data-poll-option-row]")) : [];
  }

  function updatePollOptionControlsState() {
    var rows = pollOptionRows();
    rows.forEach(function (row) {
      var removeBtn = row.querySelector("[data-poll-option-remove]");
      if (removeBtn) removeBtn.hidden = rows.length <= POLL_MIN_OPTIONS;
    });
    if (pollAddOptionBtn) {
      pollAddOptionBtn.hidden = rows.length >= POLL_MAX_OPTIONS;
    }
  }

  function addPollOptionRow(value, focus) {
    if (!pollOptionsList) return;
    if (pollOptionRows().length >= POLL_MAX_OPTIONS) return;
    pollOptionsList.insertAdjacentHTML("beforeend", pollOptionRowMarkup(value));
    updatePollOptionControlsState();
    if (focus) {
      var rows = pollOptionRows();
      var last = rows[rows.length - 1];
      var input = last && last.querySelector("[data-poll-option-input]");
      if (input) input.focus({ preventScroll: true });
    }
  }

  function togglePollMaxChoicesField() {
    if (!pollMaxChoicesField || !pollMultipleChoiceInput) return;
    pollMaxChoicesField.hidden = !pollMultipleChoiceInput.checked;
  }

  function resetPollModal() {
    if (pollQuestionInput) pollQuestionInput.value = "";
    if (pollOptionsList) pollOptionsList.innerHTML = "";
    addPollOptionRow("");
    addPollOptionRow("");
    if (pollMultipleChoiceInput) pollMultipleChoiceInput.checked = false;
    if (pollMaxChoicesInput) pollMaxChoicesInput.value = "2";
    if (pollAnonymousInput) pollAnonymousInput.checked = true;
    if (pollAllowChangeInput) pollAllowChangeInput.checked = true;
    togglePollMaxChoicesField();
  }

  function openPollComposerModal() {
    var conversation = activeConversation();
    if (!conversation || !conversation.permissions || !conversation.permissions.canCreatePoll) return;
    resetPollModal();
    openModal(pollModal, "poll");
    if (pollQuestionInput) {
      window.setTimeout(function () {
        pollQuestionInput.focus({ preventScroll: true });
      }, 0);
    }
  }

  async function createPollFromModal() {
    var conversation = activeConversation();
    if (!conversation) return;
    var question = pollQuestionInput ? toText(pollQuestionInput.value).trim() : "";
    if (!question) {
      showToast("سوال نظرسنجی را بنویس.");
      if (pollQuestionInput) pollQuestionInput.focus({ preventScroll: true });
      return;
    }

    var options = pollOptionRows().map(function (row) {
      var input = row.querySelector("[data-poll-option-input]");
      return input ? toText(input.value).trim() : "";
    }).filter(Boolean);
    if (options.length < POLL_MIN_OPTIONS) {
      showToast("حداقل دو گزینه برای نظرسنجی لازم است.");
      return;
    }

    var multipleChoice = !!(pollMultipleChoiceInput && pollMultipleChoiceInput.checked);
    var maxChoices = multipleChoice
      ? Math.max(2, Math.min(options.length, Math.floor(toNumber(pollMaxChoicesInput && pollMaxChoicesInput.value, 2))))
      : 1;
    var anonymous = !!(pollAnonymousInput && pollAnonymousInput.checked);
    var allowVoteChange = !!(pollAllowChangeInput && pollAllowChangeInput.checked);

    setModalBusy("poll", true);
    try {
      var response = await apiPost("createPoll", {
        conversationId: conversation.id,
        question: question,
        options: JSON.stringify(options),
        multipleChoice: multipleChoice ? "1" : "0",
        maxChoices: String(maxChoices),
        anonymous: anonymous ? "1" : "0",
        allowVoteChange: allowVoteChange ? "1" : "0",
        postInConversation: "1"
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ساخت نظرسنجی انجام نشد.");

      var message = normalizeMessage(response.message);
      if (message && message.conversationId === state.activeConversationId) {
        appendMessages([message], {
          replaceAll: false,
          forceStick: true,
          smooth: true,
          markNew: true
        });
      }
      closeModal(true);
      showToast("نظرسنجی ارسال شد.");
      syncConversation({ silent: true }).catch(function () {});
    } catch (error) {
      showToast(error && error.message ? error.message : "ساخت نظرسنجی انجام نشد.");
    } finally {
      setModalBusy("poll", false);
    }
  }

  function attachmentCaptionPreview(message, attachment) {
    var caption = meaningfulMessageText(message);
    var label = normalizeSpace(attachment && attachment.name);
    if (caption && label && caption !== label) {
      return caption + " • " + label;
    }
    return caption || label || "رسانه";
  }

  function messageUsesVisualCaptionLayout(message) {
    var attachments = Array.isArray(message && message.attachments) ? message.attachments : [];
    if (!attachments.length) return false;
    var previewableVisualCount = attachments.filter(function (attachment) {
      return !!attachmentPreviewPayload(attachment);
    }).length;
    return previewableVisualCount > 0 && previewableVisualCount === attachments.length;
  }

  function renderAttachmentAlbum(message, attachments) {
    var visualItems = (Array.isArray(attachments) ? attachments : []).filter(function (attachment) {
      return !!attachmentPreviewPayload(attachment);
    });
    if (visualItems.length <= 1) return "";
    var group = message && message.id ? ("message-" + message.id) : "message";
    return '<section class="msg-media-album" data-media-collection="' + escapeHtml(group) + '" data-count="' + escapeHtml(String(Math.min(visualItems.length, 6))) + '">' + visualItems.map(function (attachment) {
      return renderAttachmentMediaButton(attachment, {
        className: "msg-media-album__tile msg-attachment__media-btn",
        group: group,
        caption: attachmentCaptionPreview(message, attachment),
        messageId: message && message.id
      });
    }).join("") + "</section>";
  }

  function renderAttachment(attachment, options) {
    if (!attachment) return "";
    var opts = asObject(options) || {};
    if (attachment.category === "voice") {
      return renderVoiceAttachment(attachment);
    }
    var stateText = "";
    if (!attachment.available) {
      stateText = attachment.category === "voice"
        ? "فایل صوتی اصلی در دسترس نیست."
        : "فایل اصلی دیگر در دسترس نیست.";
    }

    var linkUrl = attachment.downloadUrl || attachment.url;
    var isPreviewMedia = attachment.category === "image" || attachment.category === "video";
    var linkAttrs = attachment.available && linkUrl
      ? ('href="' + escapeHtml(linkUrl) + '" target="_blank" rel="noopener"')
      : 'href="#" aria-disabled="true"';
    var actionsHtml = "";
    if (!isPreviewMedia || stateText) {
      actionsHtml = [
        '  <div class="msg-attachment__actions">',
        isPreviewMedia ? "" : ('    <a class="msg-attachment__link" ' + linkAttrs + ">" + (attachment.available ? "دانلود" : "ناموجود") + "</a>"),
        stateText ? ('    <span class="msg-attachment__state is-expired">' + escapeHtml(stateText) + "</span>") : "",
        "  </div>"
      ].join("");
    }

    return [
      '<article class="msg-attachment">',
      renderAttachmentMedia(attachment, {
        group: normalizeSpace(opts.mediaGroup || ""),
        caption: normalizeSpace(opts.mediaCaption || attachment.name || ""),
        messageId: opts.messageId
      }),
      '  <div class="msg-attachment__head">',
      '    <span class="msg-attachment__name" title="' + escapeHtml(attachment.name) + '">' + escapeHtml(attachment.name) + "</span>",
      '    <span class="msg-attachment__meta">' + escapeHtml(attachmentMetaText(attachment)) + "</span>",
      "  </div>",
      actionsHtml,
      "</article>"
    ].join("");
  }

  function renderAttachments(message) {
    var attachments = Array.isArray(message && message.attachments) ? message.attachments : [];
    if (!attachments.length) return "";
    var mediaGroup = message && message.id ? ("message-" + message.id) : "message";
    var visualAttachments = [];
    var remainingAttachments = [];
    attachments.forEach(function (attachment) {
      if (attachmentPreviewPayload(attachment)) {
        visualAttachments.push(attachment);
      } else {
        remainingAttachments.push(attachment);
      }
    });

    var parts = [];
    if (visualAttachments.length > 1) {
      parts.push(renderAttachmentAlbum(message, visualAttachments));
    } else if (visualAttachments.length === 1) {
      parts.push(renderAttachment(visualAttachments[0], {
        mediaGroup: mediaGroup,
        mediaCaption: attachmentCaptionPreview(message, visualAttachments[0]),
        messageId: message && message.id
      }));
    }
    remainingAttachments.forEach(function (attachment) {
      parts.push(renderAttachment(attachment, {
        mediaGroup: mediaGroup,
        mediaCaption: attachmentCaptionPreview(message, attachment),
        messageId: message && message.id
      }));
    });
    return '<div class="msg-attachments">' + parts.join("") + "</div>";
  }

  function syncVoiceNoteUi(note, audio) {
    if (!note) return;
    var playBtn = note.querySelector(".msg-voice-note__play");
    var playIcon = note.querySelector(".msg-voice-note__play-icon");
    var timeNode = note.querySelector(".msg-voice-note__time");
    var duration = Math.max(
      toNumber(audio && audio.duration, 0),
      toNumber(note.getAttribute("data-voice-duration"), 0)
    );
    var current = Math.max(0, toNumber(audio && audio.currentTime, 0));
    var fraction = duration > 0 ? clamp(current / duration, 0, 1) : 0;
    var playedBars = Math.round(fraction * VOICE_WAVE_BAR_COUNT);
    note.classList.toggle("is-playing", !!(audio && !audio.paused && !audio.ended));
    Array.from(note.querySelectorAll(".msg-voice-note__bar")).forEach(function (bar, index) {
      bar.classList.toggle("is-played", index < playedBars);
    });
    if (playBtn) {
      playBtn.setAttribute("aria-label", audio && !audio.paused && !audio.ended ? "توقف پیام صوتی" : "پخش پیام صوتی");
    }
    if (playIcon) {
      playIcon.classList.toggle("msg-voice-note__play-icon--pause", !!(audio && !audio.paused && !audio.ended));
      playIcon.classList.toggle("msg-voice-note__play-icon--play", !(audio && !audio.paused && !audio.ended));
    }
    if (timeNode) {
      if (audio && !audio.paused && duration > 0) {
        timeNode.textContent = formatDuration(current) + " / " + formatDuration(duration);
      } else {
        timeNode.textContent = formatDuration(duration);
      }
    }
  }

  function pauseAllVoiceNotes(exceptId) {
    var keepId = normalizeSpace(exceptId);
    if (!messagesEl) return;
    Array.from(messagesEl.querySelectorAll(".msg-voice-note")).forEach(function (note) {
      var noteId = normalizeSpace(note.getAttribute("data-voice-id"));
      var audio = note.querySelector(".msg-voice-note__audio");
      if (!audio || noteId === keepId) return;
      try {
        audio.pause();
      } catch (_error) {}
      audio.currentTime = 0;
      syncVoiceNoteUi(note, audio);
    });
    if (!keepId) {
      state.activeVoiceNoteId = "";
    }
  }

  function bindVoiceNote(note) {
    if (!note) return;
    var audio = note.querySelector(".msg-voice-note__audio");
    var playBtn = note.querySelector(".msg-voice-note__play");
    var speedBtn = note.querySelector(".msg-voice-note__speed");
    if (!audio || !playBtn) return;
    if (note.__voiceBound) {
      syncVoiceNoteUi(note, audio);
      return;
    }
    note.__voiceBound = true;
    note.dataset.speedIndex = "0";
    audio.preload = "metadata";
    audio.playbackRate = VOICE_PLAYBACK_SPEEDS[0];
    syncVoiceNoteUi(note, audio);

    var sync = function () {
      syncVoiceNoteUi(note, audio);
    };

    ["play", "pause", "timeupdate", "loadedmetadata", "ended", "ratechange"].forEach(function (eventName) {
      audio.addEventListener(eventName, sync);
    });
    audio.addEventListener("play", function () {
      var noteId = normalizeSpace(note.getAttribute("data-voice-id"));
      pauseAllVoiceNotes(noteId);
      state.activeVoiceNoteId = noteId;
      syncVoiceNoteUi(note, audio);
    });
    audio.addEventListener("ended", function () {
      state.activeVoiceNoteId = "";
      audio.currentTime = 0;
      syncVoiceNoteUi(note, audio);
    });

    playBtn.addEventListener("click", function () {
      if (audio.paused || audio.ended) {
        audio.play().catch(function () {});
      } else {
        audio.pause();
      }
    });

    if (speedBtn) {
      speedBtn.addEventListener("click", function () {
        var currentIndex = Math.max(0, Math.floor(toNumber(note.dataset.speedIndex, 0)));
        var nextIndex = (currentIndex + 1) % VOICE_PLAYBACK_SPEEDS.length;
        note.dataset.speedIndex = String(nextIndex);
        audio.playbackRate = VOICE_PLAYBACK_SPEEDS[nextIndex];
        speedBtn.textContent = voiceSpeedLabel(audio.playbackRate);
        syncVoiceNoteUi(note, audio);
      });
    }
  }

  function messageClass(message) {
    var mine = message.studentNumber === state.me.studentNumber;
    var classes = ["msg-item", mine ? "me" : "other"];
    if (message.pinned) classes.push("is-pinned");
    if (message.delivery === "sending") classes.push("delivery-sending");
    if (message.delivery === "failed") classes.push("delivery-failed");
    if (!mine && message.viewerImportantMentioned) classes.push("is-important-mention");
    if (state.selectedMessageIds.has(message.id)) classes.push("is-selected");
    if (state.messageSelectionMode) classes.push("is-selection-mode");
    return classes.join(" ");
  }

  function renderDeliveryMeta(message, ownMessage) {
    if (!ownMessage) return "";
    if (message.delivery === "sending") {
      return '<span class="msg-delivery is-sending">در حال ارسال</span>';
    }
    if (message.delivery === "seen") {
      var seenCount = Math.max(0, Math.floor(toNumber(message.seenByCount, 0)));
      if (seenCount > 0) {
        return '<span class="msg-delivery is-seen">✓✓ ' + seenCount.toLocaleString("fa-IR") + "</span>";
      }
      return '<span class="msg-delivery is-seen">✓✓</span>';
    }
    return '<span class="msg-delivery">✓</span>';
  }

  function messageReceiptSummary(message) {
    if (!message || message.studentNumber !== state.me.studentNumber) return "";
    if (message.delivery === "sending") return "در حال ارسال";
    if (message.delivery === "failed") return "ارسال ناموفق";
    var seenCount = Math.max(0, Math.floor(toNumber(message.seenByCount, 0)));
    if (message.delivery === "seen" || seenCount > 0) {
      if (seenCount > 0) {
        return "دیده‌شده توسط " + seenCount.toLocaleString("fa-IR") + " نفر";
      }
      return "دیده‌شده";
    }
    return "ارسال‌شده";
  }

  function renderMessage(message) {
    var row = document.createElement("article");
    row.className = messageClass(message);
    row.dataset.mid = String(message.id);
    row.dataset.sender = message.studentNumber || "";
    row.dataset.ts = String(Math.max(0, Math.floor(toNumber(message.ts, 0))));
    row.dataset.dayKey = dayKeyFromTimestamp(message.ts);
    row.dataset.dayLabel = formatDate(message.ts);

    var showRole = !!(message.canModerateChat && message.studentNumber !== state.me.studentNumber);
    var ownMessage = message.studentNumber === state.me.studentNumber;
    var showImportantMention = !!(message.viewerImportantMentioned && !ownMessage);
    var textHtml = renderMessageText(message);
    var showText = (!message.poll && !!textHtml) || (message.kind !== "poll" && !!textHtml);
    var captionAfterMedia = showText && !message.poll && messageUsesVisualCaptionLayout(message);

    row.innerHTML = [
      '<div class="msg-row">',
      '  <span class="msg-avatar" data-has-avatar="0">',
      '    <img class="msg-avatar__img" alt="" hidden>',
      '    <span class="msg-avatar__fallback">' + escapeHtml(avatarLabel(message.name)) + "</span>",
      "  </span>",
      '  <div class="msg-bubble">',
      '    <span class="msg-select-badge" aria-hidden="true">✓</span>',
      '    <div class="msg-head">',
      '      <span class="msg-name" data-digit-locale="latin">' + escapeHtml(message.name || "کاربر") + "</span>",
      showRole ? '      <span class="msg-badge">' + escapeHtml(message.roleLabel || "دانشجو") + "</span>" : "",
      "    </div>",
      showImportantMention ? '    <div class="msg-important-mention">منشن مهم برای شما</div>' : "",
      renderForwardHeader(message),
      message.replyTo ? renderReplyPreview(message.replyTo) : "",
      message.poll ? renderPollCard(message) : "",
      renderMessageMetaCard(message),
      captionAfterMedia ? "" : (showText ? ('    <div class="msg-text" data-digit-locale="latin">' + textHtml + "</div>") : ""),
      captionAfterMedia ? "" : (message.poll ? "" : renderLinkPreviews(message)),
      renderAttachments(message),
      captionAfterMedia ? ('    <div class="msg-text msg-text--caption" data-digit-locale="latin">' + textHtml + "</div>") : "",
      captionAfterMedia ? renderLinkPreviews(message) : "",
      renderReactions(message),
      '    <div class="msg-foot">',
      '      <span class="msg-flags">',
      message.pinned ? '        <span class="msg-flag">سنجاق</span>' : "",
      message.editedAt ? '        <span class="msg-flag">ویرایش‌شده</span>' : "",
      "      </span>",
      '      <span class="msg-time">' + escapeHtml(formatTime(message.ts)) + "</span>",
      renderDeliveryMeta(message, ownMessage),
      "    </div>",
      "  </div>",
      "</div>"
    ].join("");

    var avatar = row.querySelector(".msg-avatar");
    var image = row.querySelector(".msg-avatar__img");
    var fallback = row.querySelector(".msg-avatar__fallback");
    renderAvatar(avatar, image, fallback, message.avatarUrl, message.name);

    var bubble = row.querySelector(".msg-bubble");
    Array.from(row.querySelectorAll("[data-reaction-emoji]")).forEach(function (button) {
      bindReactionButton(button, message);
    });
    // Reply/forward/attachment-media/receipt clicks are handled via a single
    // delegated listener on messagesEl (see bindMessageListDelegation) so we
    // don't re-attach N listeners on every render/poll-driven re-render.
    Array.from(row.querySelectorAll(".msg-voice-note")).forEach(bindVoiceNote);
    Array.from(row.querySelectorAll(".msg-poll")).forEach(function (card) {
      bindPollCard(card, message);
    });
    var deliveryNode = row.querySelector(".msg-delivery");
    if (deliveryNode && ownMessage) {
      deliveryNode.classList.add("msg-delivery-btn");
      deliveryNode.setAttribute("role", "button");
      deliveryNode.tabIndex = 0;
    }
    attachBubbleMenuEvents(bubble, message);
    return row;
  }

  function bindMessageListDelegation() {
    if (!messagesEl) return;

    var resolveMessage = function (node) {
      var row = node && node.closest ? node.closest(".msg-item[data-mid]") : null;
      if (!row) return null;
      return state.messages.get(Number(row.dataset.mid)) || null;
    };

    messagesEl.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) return;

      var replyBtn = target.closest("[data-reply-id]");
      if (replyBtn) {
        event.preventDefault();
        event.stopPropagation();
        var targetId = Math.floor(toNumber(replyBtn.getAttribute("data-reply-id"), 0));
        if (targetId > 0) scrollToMessage(targetId);
        return;
      }

      var forwardBtn = target.closest("[data-forward-origin]");
      if (forwardBtn) {
        event.preventDefault();
        event.stopPropagation();
        var forwardMessage = resolveMessage(forwardBtn);
        if (forwardMessage) openForwardOrigin(forwardMessage);
        return;
      }

      var mediaBtn = target.closest(".msg-attachment__media-btn");
      if (mediaBtn) {
        event.preventDefault();
        event.stopPropagation();
        openMediaViewerFromNode(mediaBtn);
        return;
      }

      var deliveryBtn = target.closest(".msg-delivery-btn");
      if (deliveryBtn) {
        event.preventDefault();
        event.stopPropagation();
        var deliveryMessage = resolveMessage(deliveryBtn);
        if (deliveryMessage) openReceiptsModal(deliveryMessage);
        return;
      }

      var senderEl = target.closest(".msg-avatar, .msg-name");
      if (senderEl) {
        var msgRow = target.closest("[data-sender]");
        if (msgRow) {
          var senderStudentNumber = normalizeStudentNumber(msgRow.dataset.sender);
          var mySn = normalizeStudentNumber(state.me && state.me.studentNumber);
          if (senderStudentNumber && senderStudentNumber !== mySn) {
            event.preventDefault();
            event.stopPropagation();
            var conversation = activeConversation();
            var members = conversation && Array.isArray(conversation.members) ? conversation.members : [];
            var senderMember = members.find(function (m) { return normalizeStudentNumber(m.studentNumber) === senderStudentNumber; });
            if (!senderMember) {
              var nameEl = msgRow.querySelector(".msg-name");
              senderMember = { studentNumber: senderStudentNumber, name: nameEl ? nameEl.textContent.trim() : senderStudentNumber };
            }
            openChatMemberProfile(senderMember);
          }
        }
      }
    });

    messagesEl.addEventListener("keydown", function (event) {
      if (event.key !== "Enter" && event.key !== " ") return;
      var target = event.target;
      if (!target || !target.closest) return;

      var deliveryBtn = target.closest(".msg-delivery-btn");
      if (!deliveryBtn) return;

      event.preventDefault();
      event.stopPropagation();
      var deliveryMessage = resolveMessage(deliveryBtn);
      if (deliveryMessage) openReceiptsModal(deliveryMessage);
    });
  }

  function updatePollCardSelectionUi(card, poll) {
    if (!card || !poll) return;
    var selection = poll.settings && poll.settings.multipleChoice ? pollDraftSelection(poll) : (Array.isArray(poll.viewer && poll.viewer.selectedOptionIds) ? poll.viewer.selectedOptionIds : []);
    var selectedLookup = new Set(selection);
    Array.from(card.querySelectorAll("[data-poll-option]")).forEach(function (button) {
      var optionId = normalizeSpace(button.getAttribute("data-poll-option"));
      var selected = selectedLookup.has(optionId);
      button.classList.toggle("is-selected", selected);
      var check = button.querySelector(".msg-poll__option-check");
      if (check) {
        check.textContent = selected ? "✓" : "";
      }
    });
    var submit = card.querySelector("[data-poll-submit]");
    if (submit) {
      submit.disabled = !selection.length;
      submit.setAttribute("aria-disabled", submit.disabled ? "true" : "false");
    }
  }

  function applyPollPayloadToMessages(rawPoll) {
    var poll = normalizePoll(rawPoll);
    if (!poll) return false;
    resetPollDraftSelection(poll);
    var updated = false;
    state.messages.forEach(function (message) {
      if (!message || normalizeSpace(message.pollId) !== poll.id) return;
      message.poll = poll;
      if (message.kind !== "poll") {
        message.kind = "poll";
      }
      replaceMessageInDom(message);
      updated = true;
    });
    return updated;
  }

  async function submitPollVote(poll, optionIds, triggerNode) {
    if (!poll || !poll.id) return;
    var button = triggerNode || null;
    if (button) {
      button.disabled = true;
      button.setAttribute("aria-disabled", "true");
    }
    try {
      var response = await apiPost("votePoll", {
        pollId: poll.id,
        optionIds: JSON.stringify((Array.isArray(optionIds) ? optionIds : []).map(function (item) {
          return normalizeSpace(item);
        }).filter(Boolean))
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ثبت رأی انجام نشد.");
      if (!applyPollPayloadToMessages(response.poll)) {
        await syncConversation({
          forceFull: true,
          includeMembers: false,
          conversationId: state.activeConversationId,
          silent: true
        });
      }
      showToast("رأی ثبت شد.");
    } catch (error) {
      showToast((error && error.message) || "ثبت رأی انجام نشد.");
    } finally {
      if (button) {
        button.disabled = false;
        button.setAttribute("aria-disabled", "false");
      }
    }
  }

  async function runPollManageAction(poll, action, triggerNode) {
    if (!poll || !poll.id || !action) return;
    var button = triggerNode || null;
    if (button) {
      button.disabled = true;
      button.setAttribute("aria-disabled", "true");
    }
    try {
      var response = await apiPost(action, { pollId: poll.id });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "به‌روزرسانی نظرسنجی انجام نشد.");
      if (!applyPollPayloadToMessages(response.poll)) {
        await syncConversation({
          forceFull: true,
          includeMembers: false,
          conversationId: state.activeConversationId,
          silent: true
        });
      }
      showToast(action === "closePoll" ? "نظرسنجی بسته شد." : "نظرسنجی بازگشایی شد.");
    } catch (error) {
      showToast((error && error.message) || "به‌روزرسانی نظرسنجی انجام نشد.");
    } finally {
      if (button) {
        button.disabled = false;
        button.setAttribute("aria-disabled", "false");
      }
    }
  }

  function bindPollCard(card, message) {
    if (!card || !message || !message.poll) return;
    var poll = message.poll;
    updatePollCardSelectionUi(card, poll);
    Array.from(card.querySelectorAll("[data-poll-option]")).forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (!(poll.viewer && poll.viewer.canVote)) return;
        var optionId = normalizeSpace(button.getAttribute("data-poll-option"));
        if (!optionId) return;
        if (!(poll.settings && poll.settings.multipleChoice)) {
          submitPollVote(poll, [optionId], button);
          return;
        }
        var selection = pollDraftSelection(poll);
        var next = selection.filter(function (item) { return item !== optionId; });
        if (next.length === selection.length) {
          next.push(optionId);
        }
        if (poll.settings.maxChoices > 0 && next.length > poll.settings.maxChoices) {
          showToast("حداکثر " + poll.settings.maxChoices.toLocaleString("fa-IR") + " گزینه قابل انتخاب است.");
          return;
        }
        setPollDraftSelection(poll.id, next);
        updatePollCardSelectionUi(card, poll);
      });
    });
    var submit = card.querySelector("[data-poll-submit]");
    if (submit) {
      submit.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        submitPollVote(poll, pollDraftSelection(poll), submit);
      });
    }
    var reset = card.querySelector("[data-poll-reset]");
    if (reset) {
      reset.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        resetPollDraftSelection(poll);
        updatePollCardSelectionUi(card, poll);
      });
    }
    var closeBtn = card.querySelector("[data-poll-close]");
    if (closeBtn) {
      closeBtn.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        runPollManageAction(poll, "closePoll", closeBtn);
      });
    }
    var reopenBtn = card.querySelector("[data-poll-reopen]");
    if (reopenBtn) {
      reopenBtn.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        runPollManageAction(poll, "reopenPoll", reopenBtn);
      });
    }
  }

  function updateMessageGroups() {
    if (!messagesEl) return;
    var items = Array.from(messagesEl.querySelectorAll(".msg-item"));
    var groupGapSeconds = 8 * 60;
    items.forEach(function (item, index) {
      item.classList.remove("group-start", "group-middle", "group-end", "group-single", "grouped-with-prev", "day-start");

      var currentSender = item.dataset.sender;
      var prev = index > 0 ? items[index - 1] : null;
      var next = index < items.length - 1 ? items[index + 1] : null;
      var currentTs = toNumber(item.dataset.ts, 0);
      var prevTs = prev ? toNumber(prev.dataset.ts, 0) : 0;
      var nextTs = next ? toNumber(next.dataset.ts, 0) : 0;
      var sameDayWithPrev = !!(prev && prev.dataset.dayKey === item.dataset.dayKey);
      var sameDayWithNext = !!(next && next.dataset.dayKey === item.dataset.dayKey);
      var closeToPrev = sameDayWithPrev && currentTs > 0 && prevTs > 0 && Math.abs(currentTs - prevTs) <= groupGapSeconds;
      var closeToNext = sameDayWithNext && currentTs > 0 && nextTs > 0 && Math.abs(nextTs - currentTs) <= groupGapSeconds;

      if (item.dataset.dayLabel && !sameDayWithPrev) {
        item.classList.add("day-start");
      }

      var samePrev = !!(prev && prev.dataset.sender === currentSender && closeToPrev);
      var sameNext = !!(next && next.dataset.sender === currentSender && closeToNext);

      if (samePrev) item.classList.add("grouped-with-prev");
      if (!samePrev && !sameNext) {
        item.classList.add("group-single");
      } else if (!samePrev && sameNext) {
        item.classList.add("group-start");
      } else if (samePrev && sameNext) {
        item.classList.add("group-middle");
      } else {
        item.classList.add("group-end");
      }
    });
  }

  function renderUnreadDivider() {
    if (!messagesEl) return;
    Array.from(messagesEl.querySelectorAll(".msg-unread-divider")).forEach(function (node) {
      node.remove();
    });
    var markerId = Math.max(0, Math.floor(toNumber(state.unreadDividerMessageId, 0)));
    if (!markerId) return;
    var target = messagesEl.querySelector('[data-mid="' + markerId + '"]');
    if (!target) return;
    var divider = document.createElement("div");
    divider.className = "msg-unread-divider";
    divider.innerHTML = '<span>پیام‌های خوانده‌نشده</span>';
    messagesEl.insertBefore(divider, target);
  }

  function focusMessageHighlight(messageId, durationMs) {
    state.contextAnchorMessageId = Number(messageId) || null;
    syncMessageFocus();
    window.setTimeout(function () {
      if (state.contextAnchorMessageId !== Number(messageId)) return;
      state.contextAnchorMessageId = null;
      syncMessageFocus();
    }, Math.max(900, Math.floor(toNumber(durationMs, 1800))));
  }

  function syncMessageFocus() {
    if (!messagesEl) return;
    var anchor = state.contextAnchorMessageId;
    Array.from(messagesEl.querySelectorAll(".msg-item")).forEach(function (item) {
      if (anchor && item.dataset.mid === String(anchor)) {
        item.classList.add("is-menu-open");
      } else {
        item.classList.remove("is-menu-open");
      }
    });
  }

  function scrollToBottom(smooth) {
    if (!messagesEl) return;
    state.threadAutoStick = true;
    messagesEl.scrollTo({
      top: messagesEl.scrollHeight,
      behavior: smooth ? "smooth" : "auto"
    });
  }

  function isThreadNearBottom(threshold) {
    if (!messagesEl) return true;
    var offset = toNumber(threshold, 110);
    return (messagesEl.scrollTop + messagesEl.clientHeight + offset) >= messagesEl.scrollHeight;
  }

  function bindMediaAutoStick(node) {
    if (!node || !messagesEl) return;
    var mediaNodes = Array.from(node.querySelectorAll(".msg-attachment__media img, .msg-attachment__media video"));
    mediaNodes.forEach(function (media) {
      if (!media) return;
      var isReady = media.tagName === "IMG"
        ? !!media.complete
        : (toNumber(media.readyState, 0) >= 1);
      if (isReady) return;

      var handleReady = function () {
        media.removeEventListener("load", handleReady);
        media.removeEventListener("loadedmetadata", handleReady);
        media.removeEventListener("error", handleReady);
        if (!isThreadNearBottom(140)) return;
        window.requestAnimationFrame(function () {
          scrollToBottom(false);
        });
      };

      media.addEventListener("load", handleReady, { once: true });
      media.addEventListener("loadedmetadata", handleReady, { once: true });
      media.addEventListener("error", handleReady, { once: true });
    });
  }

  function scrollToMessage(messageId, options) {
    if (!messagesEl || !messageId) return;
    var node = messagesEl.querySelector('[data-mid="' + Number(messageId) + '"]');
    if (!node) return;
    var opts = asObject(options) || {};
    node.scrollIntoView({ behavior: opts.behavior || "smooth", block: opts.block || "center" });
    focusMessageHighlight(messageId, opts.durationMs || 1800);
  }

  function normalizeThreadDayItem(raw) {
    var source = asObject(raw) || {};
    var messageId = Math.max(0, Math.floor(toNumber(source.messageId, 0)));
    if (!messageId) return null;
    return {
      key: normalizeSpace(source.key || ""),
      ts: Math.max(0, Math.floor(toNumber(source.ts, 0))),
      messageId: messageId,
      count: Math.max(0, Math.floor(toNumber(source.count, 0)))
    };
  }

  function normalizeThreadSearchResult(raw) {
    var source = asObject(raw) || {};
    var messageId = Math.max(0, Math.floor(toNumber(source.messageId, 0)));
    if (!messageId) return null;
    return {
      messageId: messageId,
      ts: Math.max(0, Math.floor(toNumber(source.ts, 0))),
      senderName: normalizeSpace(source.senderName || source.name || "کاربر"),
      excerpt: normalizeSpace(source.excerpt || source.text || ""),
      kind: normalizeSpace(source.kind || "text"),
      dayKey: normalizeSpace(source.dayKey || "")
    };
  }

  function currentThreadSearchResult() {
    if (!Array.isArray(state.threadSearchResults) || !state.threadSearchResults.length) return null;
    var index = clamp(Math.floor(toNumber(state.threadSearchIndex, 0)), 0, state.threadSearchResults.length - 1);
    return state.threadSearchResults[index] || null;
  }

  function resetThreadSearchState(options) {
    var opts = asObject(options) || {};
    if (state.threadSearchDebounceTimer) {
      window.clearTimeout(state.threadSearchDebounceTimer);
      state.threadSearchDebounceTimer = null;
    }
    state.threadSearchRequestToken += 1;
    state.threadSearchQuery = "";
    state.threadSearchResults = [];
    state.threadSearchIndex = -1;
    if (!opts.keepNavigator) {
      state.threadNavigatorConversationId = "";
      state.threadNavigatorDays = [];
      state.threadNavigatorFirstUnreadMessageId = 0;
    }
    if (!opts.keepPanel) {
      state.threadSearchOpen = false;
    }
    if (threadSearchInput && !opts.keepInputValue) {
      threadSearchInput.value = "";
    }
  }

  function renderThreadDayOptions() {
    if (!threadDaySelect) return;
    var previousValue = normalizeSpace(threadDaySelect.value);
    var days = Array.isArray(state.threadNavigatorDays) ? state.threadNavigatorDays.slice() : [];
    threadDaySelect.innerHTML = ['<option value="">انتخاب روز</option>'].concat(days.map(function (day) {
      var countText = day.count > 0 ? (" • " + day.count.toLocaleString("fa-IR") + " پیام") : "";
      var label = formatDate(day.ts) || day.key || "روز";
      return '<option value="' + escapeHtml(String(day.messageId)) + '">' + escapeHtml(label + countText) + "</option>";
    })).join("");
    if (previousValue && threadDaySelect.querySelector('option[value="' + previousValue + '"]')) {
      threadDaySelect.value = previousValue;
    }
  }

  function updateThreadSearchUi() {
    var hasConversation = !!activeConversation();
    if (threadSearchToggle) {
      threadSearchToggle.disabled = !hasConversation;
      threadSearchToggle.classList.toggle("is-active", !!(state.threadSearchOpen && hasConversation));
    }
    if (!threadSearchPanel) return;
    threadSearchPanel.hidden = !(state.threadSearchOpen && hasConversation);
    if (!state.threadSearchOpen || !hasConversation) {
      return;
    }
    var hasResults = Array.isArray(state.threadSearchResults) && state.threadSearchResults.length > 0;
    var selected = currentThreadSearchResult();
    if (threadSearchPrev) threadSearchPrev.disabled = !hasResults;
    if (threadSearchNext) threadSearchNext.disabled = !hasResults;
    if (threadSearchCount) {
      if (normalizeSpace(state.threadSearchQuery) && hasResults) {
        threadSearchCount.textContent = (Math.max(0, state.threadSearchIndex) + 1).toLocaleString("fa-IR") + " از " + state.threadSearchResults.length.toLocaleString("fa-IR");
      } else if (normalizeSpace(state.threadSearchQuery)) {
        threadSearchCount.textContent = "نتیجه‌ای پیدا نشد";
      } else {
        threadSearchCount.textContent = "جستجو در گفتگو";
      }
    }
    if (threadSearchPreview) {
      if (selected) {
        threadSearchPreview.hidden = false;
        threadSearchPreview.innerHTML = [
          "<strong>" + escapeHtml(selected.senderName || "کاربر") + "</strong>",
          "<span>" + escapeHtml(selected.excerpt || "پرش به پیام") + "</span>",
          "<small>" + escapeHtml(formatDateTime(selected.ts)) + "</small>"
        ].join("");
      } else {
        threadSearchPreview.hidden = true;
        threadSearchPreview.innerHTML = "";
      }
    }
    renderThreadDayOptions();
    if (threadJumpUnreadBtn) {
      var firstUnreadId = Math.max(0, Math.floor(toNumber(state.threadNavigatorFirstUnreadMessageId || state.unreadDividerMessageId, 0)));
      threadJumpUnreadBtn.disabled = !firstUnreadId;
      threadJumpUnreadBtn.textContent = firstUnreadId ? "اولین خوانده‌نشده" : "خوانده‌نشده‌ای نیست";
    }
    if (threadJumpDayBtn) {
      threadJumpDayBtn.disabled = !(threadDaySelect && normalizeSpace(threadDaySelect.value));
    }
  }

  function setThreadSearchOpen(open) {
    var nextOpen = !!open;
    if (nextOpen && !activeConversation()) {
      return;
    }
    state.threadSearchOpen = nextOpen;
    if (!nextOpen) {
      resetThreadSearchState({ keepNavigator: true, keepPanel: false });
    }
    updateThreadSearchUi();
    if (nextOpen) {
      fetchThreadNavigator().catch(function () {});
      window.requestAnimationFrame(function () {
        if (threadSearchInput) {
          threadSearchInput.focus({ preventScroll: true });
          threadSearchInput.select();
        }
      });
    }
  }

  async function fetchThreadNavigator() {
    var conversation = activeConversation();
    if (!conversation) return null;
    var response = await apiGet("threadNavigator", {
      conversationId: conversation.id
    }, { quiet: true });
    if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
      throw new Error((response && response.error) || "نشست شما منقضی شده است.");
    }
    ensureSuccessResponse(response, "پیمایش گفتگو دریافت نشد.");
    if (!activeConversation() || activeConversation().id !== conversation.id) {
      return null;
    }
    var navigatorPayload = asObject(response.navigator) || {};
    state.threadNavigatorConversationId = conversation.id;
    state.threadNavigatorDays = (Array.isArray(navigatorPayload.days) ? navigatorPayload.days : [])
      .map(normalizeThreadDayItem)
      .filter(Boolean)
      .sort(function (left, right) {
        return toNumber(right.ts, 0) - toNumber(left.ts, 0);
      });
    state.threadNavigatorFirstUnreadMessageId = Math.max(0, Math.floor(toNumber(navigatorPayload.firstUnreadMessageId, 0)));
    if (!state.unreadDividerMessageId && state.threadNavigatorFirstUnreadMessageId > 0) {
      state.unreadDividerMessageId = state.threadNavigatorFirstUnreadMessageId;
    }
    updateThreadSearchUi();
    return navigatorPayload;
  }

  async function loadThreadContext(messageId, options) {
    var conversation = activeConversation();
    var targetId = Math.max(0, Math.floor(toNumber(messageId, 0)));
    if (!conversation || !targetId) return false;
    var existing = messagesEl ? messagesEl.querySelector('[data-mid="' + targetId + '"]') : null;
    if (existing) {
      scrollToMessage(targetId, options);
      return true;
    }
    var response = await apiGet("threadContext", {
      conversationId: conversation.id,
      messageId: String(targetId),
      before: String(THREAD_CONTEXT_BEFORE_LIMIT),
      after: String(THREAD_CONTEXT_AFTER_LIMIT)
    }, { quiet: true });
    if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
      throw new Error((response && response.error) || "نشست شما منقضی شده است.");
    }
    ensureSuccessResponse(response, "بخش موردنظر گفتگو دریافت نشد.");
    if (!activeConversation() || activeConversation().id !== conversation.id) {
      return false;
    }
    var page = asObject(response.messagePage) || {};
    state.hasMoreBefore = page.hasMoreBefore === true;
    state.unreadDividerMessageId = Math.max(0, Math.floor(toNumber(page.firstUnreadMessageId, 0)));
    appendMessages((Array.isArray(response.messages) ? response.messages : []).map(normalizeMessage).filter(Boolean), {
      replaceAll: true,
      prepend: false,
      forceStick: false,
      smooth: false,
      markNew: false
    });
    window.requestAnimationFrame(function () {
      scrollToMessage(targetId, options);
    });
    return true;
  }

  async function goToThreadSearchResult(index) {
    if (!Array.isArray(state.threadSearchResults) || !state.threadSearchResults.length) return;
    var nextIndex = index;
    if (nextIndex < 0) {
      nextIndex = state.threadSearchResults.length - 1;
    }
    if (nextIndex >= state.threadSearchResults.length) {
      nextIndex = 0;
    }
    state.threadSearchIndex = nextIndex;
    updateThreadSearchUi();
    var result = currentThreadSearchResult();
    if (!result) return;
    await loadThreadContext(result.messageId, { behavior: "smooth", block: "center", durationMs: 2200 });
  }

  async function runThreadSearch(query) {
    var conversation = activeConversation();
    var q = normalizeSpace(query);
    state.threadSearchQuery = q;
    if (!conversation || !q) {
      state.threadSearchResults = [];
      state.threadSearchIndex = -1;
      updateThreadSearchUi();
      return null;
    }
    var token = ++state.threadSearchRequestToken;
    var response = await apiGet("messageSearch", {
      conversationId: conversation.id,
      q: q,
      limit: String(THREAD_SEARCH_RESULT_LIMIT)
    }, { quiet: true });
    if (token !== state.threadSearchRequestToken) {
      return null;
    }
    if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
      throw new Error((response && response.error) || "نشست شما منقضی شده است.");
    }
    ensureSuccessResponse(response, "جستجوی گفتگو انجام نشد.");
    if (!activeConversation() || activeConversation().id !== conversation.id) {
      return null;
    }
    state.threadSearchResults = (Array.isArray(response.results) ? response.results : [])
      .map(normalizeThreadSearchResult)
      .filter(Boolean);
    state.threadSearchIndex = state.threadSearchResults.length ? 0 : -1;
    updateThreadSearchUi();
    return state.threadSearchResults;
  }

  function scheduleThreadSearch() {
    if (state.threadSearchDebounceTimer) {
      window.clearTimeout(state.threadSearchDebounceTimer);
    }
    state.threadSearchDebounceTimer = window.setTimeout(function () {
      state.threadSearchDebounceTimer = null;
      runThreadSearch(threadSearchInput ? threadSearchInput.value : "").catch(function (error) {
        updateThreadSearchUi();
        showToast((error && error.message) || "جستجوی گفتگو انجام نشد.");
      });
    }, THREAD_SEARCH_DEBOUNCE_MS);
  }

  function closeMediaViewer() {
    if (!mediaViewer) return;
    if (mediaViewerCloseTimer) {
      window.clearTimeout(mediaViewerCloseTimer);
      mediaViewerCloseTimer = null;
    }
    if (mediaViewer.hidden) return;
    mediaViewer.classList.remove("is-open");
    mediaViewer.classList.remove("is-zoomed");
    if (mediaViewerStage) {
      mediaViewerStage.classList.remove("is-dragging");
      mediaViewerStage.classList.remove("is-settling");
      mediaViewerStage.style.removeProperty("transform");
      mediaViewerStage.style.removeProperty("opacity");
    }
    state.mediaViewerItems = [];
    state.mediaViewerIndex = -1;
    state.mediaViewerZoomed = false;
    state.mediaViewerPointer = null;
    state.mediaViewerScale = 1;
    state.mediaViewerRotation = 0;
    state.mediaViewerOffsetX = 0;
    state.mediaViewerOffsetY = 0;
    state.mediaViewerActivePointers.clear();
    state.mediaViewerPinch = null;
    state.mediaViewerPanPointer = null;
    mediaViewerCloseTimer = window.setTimeout(function () {
      mediaViewerCloseTimer = null;
      mediaViewer.hidden = true;
      if (mediaViewerStage) mediaViewerStage.innerHTML = "";
      if (mediaViewerCaption) mediaViewerCaption.textContent = "";
      if (mediaViewerCounter) mediaViewerCounter.textContent = "";
      if (mediaViewerMeta) mediaViewerMeta.hidden = true;
      if (mediaViewerSender) mediaViewerSender.textContent = "";
      if (mediaViewerTime) mediaViewerTime.textContent = "";
      if (mediaViewerForward) mediaViewerForward.hidden = true;
      if (mediaViewerDelete) mediaViewerDelete.hidden = true;
      if (mediaViewerRotate) mediaViewerRotate.hidden = true;
      if (mediaViewerDownload) {
        mediaViewerDownload.hidden = true;
        mediaViewerDownload.removeAttribute("href");
      }
    }, MEDIA_VIEWER_FADE_MS);
  }

  function clampMediaViewerOffsets() {
    if (!mediaViewerStage) return;
    var media = mediaViewerStage.querySelector("img, video");
    if (!media) return;
    var stageRect = mediaViewerStage.getBoundingClientRect();
    var rotated = (state.mediaViewerRotation % 180) !== 0;
    var baseW = media.offsetWidth;
    var baseH = media.offsetHeight;
    var renderedW = (rotated ? baseH : baseW) * state.mediaViewerScale;
    var renderedH = (rotated ? baseW : baseH) * state.mediaViewerScale;
    var maxX = Math.max(0, (renderedW - stageRect.width) / 2);
    var maxY = Math.max(0, (renderedH - stageRect.height) / 2);
    state.mediaViewerOffsetX = clamp(state.mediaViewerOffsetX, -maxX, maxX);
    state.mediaViewerOffsetY = clamp(state.mediaViewerOffsetY, -maxY, maxY);
  }

  function applyMediaViewerTransform(options) {
    if (!mediaViewerStage) return;
    var media = mediaViewerStage.querySelector("img, video");
    if (!media) return;
    var opts = asObject(options) || {};
    clampMediaViewerOffsets();
    media.style.transition = opts.live ? "none" : "";
    media.style.transform = "translate3d(" + state.mediaViewerOffsetX.toFixed(1) + "px," + state.mediaViewerOffsetY.toFixed(1) + "px,0) rotate(" + state.mediaViewerRotation + "deg) scale(" + state.mediaViewerScale.toFixed(3) + ")";
    var zoomed = state.mediaViewerScale > 1.01;
    state.mediaViewerZoomed = zoomed;
    mediaViewer.classList.toggle("is-zoomed", zoomed);
  }

  function setMediaViewerScale(scale, options) {
    state.mediaViewerScale = clamp(scale, 1, 4);
    if (state.mediaViewerScale <= 1.01) {
      state.mediaViewerScale = 1;
      state.mediaViewerOffsetX = 0;
      state.mediaViewerOffsetY = 0;
    }
    applyMediaViewerTransform(options);
  }

  function setMediaViewerScaleAtPoint(scale, clientX, clientY, options) {
    if (!mediaViewerStage || typeof clientX !== "number" || typeof clientY !== "number") {
      setMediaViewerScale(scale, options);
      return;
    }
    var newScale = clamp(scale, 1, 4);
    if (newScale <= 1.01) {
      state.mediaViewerScale = 1;
      state.mediaViewerOffsetX = 0;
      state.mediaViewerOffsetY = 0;
      applyMediaViewerTransform(options);
      return;
    }
    var rect = mediaViewerStage.getBoundingClientRect();
    var stageCenterX = rect.left + rect.width / 2;
    var stageCenterY = rect.top + rect.height / 2;
    var anchorX = (clientX - stageCenterX - state.mediaViewerOffsetX) / state.mediaViewerScale;
    var anchorY = (clientY - stageCenterY - state.mediaViewerOffsetY) / state.mediaViewerScale;
    state.mediaViewerOffsetX = clientX - stageCenterX - anchorX * newScale;
    state.mediaViewerOffsetY = clientY - stageCenterY - anchorY * newScale;
    state.mediaViewerScale = newScale;
    applyMediaViewerTransform(options);
  }

  function rotateMediaViewerImage() {
    var item = state.mediaViewerItems[state.mediaViewerIndex];
    if (!item || item.kind !== "image") return;
    state.mediaViewerRotation = (state.mediaViewerRotation + 90) % 360;
    applyMediaViewerTransform();
  }

  function ensureMediaViewerControls() {
    if (!mediaViewer) return;
    if (!mediaViewerPrev) {
      mediaViewerPrev = document.createElement("button");
      mediaViewerPrev.type = "button";
      mediaViewerPrev.className = "chat-media-viewer__nav chat-media-viewer__nav--prev";
      mediaViewerPrev.setAttribute("aria-label", "\u0631\u0633\u0627\u0646\u0647 \u0642\u0628\u0644\u06cc");
      mediaViewerPrev.innerHTML = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 5L8 12L15 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      mediaViewerPrev.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        stepMediaViewer(-1);
      });
      mediaViewer.appendChild(mediaViewerPrev);
    }
    if (!mediaViewerNext) {
      mediaViewerNext = document.createElement("button");
      mediaViewerNext.type = "button";
      mediaViewerNext.className = "chat-media-viewer__nav chat-media-viewer__nav--next";
      mediaViewerNext.setAttribute("aria-label", "\u0631\u0633\u0627\u0646\u0647 \u0628\u0639\u062f\u06cc");
      mediaViewerNext.innerHTML = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 5L16 12L9 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      mediaViewerNext.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        stepMediaViewer(1);
      });
      mediaViewer.appendChild(mediaViewerNext);
    }
    if (!mediaViewerCounter) {
      mediaViewerCounter = document.createElement("div");
      mediaViewerCounter.className = "chat-media-viewer__counter";
      mediaViewer.appendChild(mediaViewerCounter);
    }
  }

  function mediaViewerItemFromNode(node) {
    if (!node) return null;
    var kind = normalizeSpace(node.getAttribute("data-media-kind"));
    var src = toText(node.getAttribute("data-media-src"));
    var poster = toText(node.getAttribute("data-media-poster"));
    var caption = normalizeSpace(node.getAttribute("data-media-caption"));
    if (!src) return null;
    if (kind !== "video") kind = "image";
    var messageId = Math.floor(toNumber(node.getAttribute("data-media-message-id"), 0));
    var message = messageId > 0 ? findMessage(messageId) : null;
    return {
      kind: kind,
      src: src,
      poster: poster,
      caption: caption,
      messageId: messageId > 0 ? messageId : 0,
      downloadUrl: toText(node.getAttribute("data-media-download")) || src,
      senderName: message ? (message.name || "") : "",
      ts: message ? message.ts : 0
    };
  }

  function resetMediaViewerStageOffset() {
    if (!mediaViewerStage) return;
    mediaViewerStage.classList.remove("is-dragging");
    mediaViewerStage.classList.remove("is-settling");
    mediaViewerStage.style.removeProperty("transform");
    mediaViewerStage.style.removeProperty("opacity");
  }

  function applyMediaViewerStageOffset(offsetPx) {
    if (!mediaViewerStage) return;
    var offset = toNumber(offsetPx, 0);
    if (Math.abs(offset) < 0.5) {
      resetMediaViewerStageOffset();
      return;
    }
    var limited = clamp(offset, -window.innerWidth * 0.42, window.innerWidth * 0.42);
    var opacity = clamp(1 - (Math.abs(limited) / Math.max(320, window.innerWidth * 0.85)), 0.76, 1);
    mediaViewerStage.classList.add("is-dragging");
    mediaViewerStage.classList.remove("is-settling");
    mediaViewerStage.style.transform = "translate3d(" + limited.toFixed(1) + "px,0,0)";
    mediaViewerStage.style.opacity = opacity.toFixed(3);
  }

  function settleMediaViewerStep(direction, distancePx) {
    if (!mediaViewerStage) {
      stepMediaViewer(direction);
      return;
    }
    var sign = direction > 0 ? -1 : 1;
    var distance = Math.max(window.innerWidth * 0.18, Math.abs(toNumber(distancePx, 0)));
    mediaViewerStage.classList.remove("is-dragging");
    mediaViewerStage.classList.add("is-settling");
    mediaViewerStage.style.transform = "translate3d(" + (sign * distance).toFixed(1) + "px,0,0)";
    mediaViewerStage.style.opacity = "0.78";
    window.setTimeout(function () {
      stepMediaViewer(direction);
    }, MEDIA_VIEWER_SETTLE_MS);
  }

  function collectMediaViewerItems(activeNode) {
    var selector = ".msg-attachment__media-btn[data-media-src], .composer-upload-item__preview[data-media-src]";
    var group = normalizeSpace(activeNode && activeNode.getAttribute("data-media-group"));
    var nodes = [];
    var scopedCollection = activeNode && activeNode.closest ? activeNode.closest("[data-media-collection]") : null;
    if (scopedCollection) {
      nodes = Array.from(scopedCollection.querySelectorAll(selector));
    }
    if (group && nodes.length) {
      nodes = nodes.filter(function (node) {
        return normalizeSpace(node.getAttribute("data-media-group")) === group;
      });
    }
    if (!nodes.length) {
      var messageScope = activeNode && activeNode.closest ? activeNode.closest(".msg-item, .composer-uploads") : null;
      if (messageScope) {
        nodes = Array.from(messageScope.querySelectorAll(selector));
      }
    }
    if (group && nodes.length) {
      nodes = nodes.filter(function (node) {
        return normalizeSpace(node.getAttribute("data-media-group")) === group;
      });
    }
    if (!nodes.length && messagesEl) {
      nodes = Array.from(messagesEl.querySelectorAll(selector));
    }
    if (!nodes.length && composerUploads) {
      nodes = Array.from(composerUploads.querySelectorAll(selector));
    }
    var items = nodes.map(mediaViewerItemFromNode).filter(Boolean);
    var index = nodes.indexOf(activeNode);
    if (index < 0) index = 0;
    if (!items.length) {
      var fallback = mediaViewerItemFromNode(activeNode);
      if (fallback) {
        items = [fallback];
        index = 0;
      }
    }
    return {
      items: items,
      index: clamp(index, 0, Math.max(0, items.length - 1))
    };
  }

  function renderMediaViewerItem() {
    if (!mediaViewer || !mediaViewerStage) return;
    var item = state.mediaViewerItems[state.mediaViewerIndex];
    if (!item || !item.src) return;

    state.mediaViewerZoomed = false;
    state.mediaViewerScale = 1;
    state.mediaViewerRotation = 0;
    state.mediaViewerPinch = null;
    state.mediaViewerPanPointer = null;
    state.mediaViewerActivePointers.clear();
    mediaViewer.classList.remove("is-zoomed");
    resetMediaViewerStageOffset();

    mediaViewerStage.innerHTML = item.kind === "video"
      ? '<video controls autoplay playsinline preload="metadata" src="' + escapeHtml(item.src) + '"' + (item.poster ? ' poster="' + escapeHtml(item.poster) + '"' : "") + "></video>"
      : '<img src="' + escapeHtml(item.src) + '" decoding="async" alt="' + escapeHtml(item.caption || "\u0631\u0633\u0627\u0646\u0647") + '">';
    if (mediaViewerRotate) mediaViewerRotate.hidden = item.kind !== "image";
    if (mediaViewerCaption) mediaViewerCaption.textContent = item.caption || "";
    if (mediaViewerCounter) {
      mediaViewerCounter.textContent = state.mediaViewerItems.length > 1
        ? ((state.mediaViewerIndex + 1).toLocaleString("fa-IR") + " / " + state.mediaViewerItems.length.toLocaleString("fa-IR"))
        : "";
      mediaViewerCounter.hidden = state.mediaViewerItems.length <= 1;
    }
    if (mediaViewerPrev) mediaViewerPrev.hidden = state.mediaViewerItems.length <= 1;
    if (mediaViewerNext) mediaViewerNext.hidden = state.mediaViewerItems.length <= 1;

    var message = item.messageId ? findMessage(item.messageId) : null;
    if (mediaViewerSender) mediaViewerSender.textContent = item.senderName || "";
    if (mediaViewerTime) mediaViewerTime.textContent = item.ts ? formatDateTime(item.ts) : "";
    if (mediaViewerMeta) mediaViewerMeta.hidden = !item.senderName && !item.ts;
    if (mediaViewerDownload) {
      if (item.downloadUrl) {
        mediaViewerDownload.href = item.downloadUrl;
        mediaViewerDownload.hidden = false;
      } else {
        mediaViewerDownload.hidden = true;
        mediaViewerDownload.removeAttribute("href");
      }
    }
    if (mediaViewerForward) mediaViewerForward.hidden = !message;
    if (mediaViewerDelete) mediaViewerDelete.hidden = !message || !canManageMessage(message);
  }

  function stepMediaViewer(delta) {
    var total = state.mediaViewerItems.length;
    if (total <= 1) return;
    var next = state.mediaViewerIndex + (delta < 0 ? -1 : 1);
    if (next < 0) next = total - 1;
    if (next >= total) next = 0;
    state.mediaViewerIndex = next;
    renderMediaViewerItem();
  }

  function toggleMediaViewerZoom(event) {
    if (!mediaViewer || !mediaViewerStage) return;
    var item = state.mediaViewerItems[state.mediaViewerIndex];
    if (!item || item.kind !== "image") return;
    if (state.mediaViewerScale > 1.01) {
      setMediaViewerScale(1);
      return;
    }
    if (event && typeof event.clientX === "number") {
      setMediaViewerScaleAtPoint(2.5, event.clientX, event.clientY);
    } else {
      setMediaViewerScale(2.5);
    }
  }

  function openMediaViewerFromNode(node) {
    if (!node || !mediaViewer || !mediaViewerStage) return;
    if (mediaViewerCloseTimer) {
      window.clearTimeout(mediaViewerCloseTimer);
      mediaViewerCloseTimer = null;
    }
    var payload = collectMediaViewerItems(node);
    if (payload.items.length) {
      ensureMediaViewerControls();
      state.mediaViewerItems = payload.items;
      state.mediaViewerIndex = payload.index;
      renderMediaViewerItem();
      mediaViewer.hidden = false;
      window.requestAnimationFrame(function () {
        mediaViewer.classList.add("is-open");
      });
      return;
    }
    var kind = normalizeSpace(node.getAttribute("data-media-kind"));
    var src = toText(node.getAttribute("data-media-src"));
    var poster = toText(node.getAttribute("data-media-poster"));
    var caption = normalizeSpace(node.getAttribute("data-media-caption"));
    if (!src) return;

    mediaViewerStage.innerHTML = kind === "video"
      ? '<video controls autoplay playsinline src="' + escapeHtml(src) + '"' + (poster ? ' poster="' + escapeHtml(poster) + '"' : "") + "></video>"
      : '<img src="' + escapeHtml(src) + '" alt="' + escapeHtml(caption || "رسانه") + '">';
    if (mediaViewerCaption) mediaViewerCaption.textContent = caption || "";
    if (mediaViewerMeta) mediaViewerMeta.hidden = true;
    if (mediaViewerForward) mediaViewerForward.hidden = true;
    if (mediaViewerDelete) mediaViewerDelete.hidden = true;
    if (mediaViewerDownload) {
      var downloadUrl = toText(node.getAttribute("data-media-download")) || src;
      mediaViewerDownload.href = downloadUrl;
      mediaViewerDownload.hidden = false;
    }
    mediaViewer.hidden = false;
    window.requestAnimationFrame(function () {
      mediaViewer.classList.add("is-open");
    });
  }

  function renderReceiptRow(entry, seen) {
    var user = normalizeUser(entry && entry.user) || {
      studentNumber: "",
      name: "کاربر",
      profile: { avatarUrl: "" }
    };
    var seenAt = entry && entry.seenAt ? formatDateTime(entry.seenAt) : "";
    return [
      '<div class="receipt-row">',
      '  <span class="receipt-row__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(user.name)) + '</span></span>',
      '  <span class="receipt-row__copy">',
      '    <strong>' + escapeHtml(user.name) + '</strong>',
      '    <small>' + escapeHtml(seen ? (seenAt || "مشاهده شده") : "هنوز مشاهده نشده") + '</small>',
      '  </span>',
      '  <span class="receipt-row__state' + (seen ? " is-seen" : "") + '">' + (seen ? "سین" : "ارسال") + '</span>',
      '</div>'
    ].join("");
  }

  function hydrateReceiptAvatars(container, entries, offset) {
    if (!container) return;
    var rows = Array.from(container.querySelectorAll(".receipt-row"));
    entries.forEach(function (entry, index) {
      var row = rows[index + (offset || 0)];
      var user = normalizeUser(entry && entry.user);
      if (!row || !user) return;
      var avatar = row.querySelector(".receipt-row__avatar");
      var image = row.querySelector("img");
      var fallback = row.querySelector(".receipt-row__avatar span");
      renderAvatar(avatar, image, fallback, user.profile && user.profile.avatarUrl, user.name);
    });
  }

  function normalizeReactionDetailEntry(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var user = normalizeUser(source.user);
    if (!user) return null;
    return {
      user: user,
      reactedAt: source.reactedAt != null ? Math.floor(toNumber(source.reactedAt, 0)) : null
    };
  }

  function renderReactionDetailRow(entry, emoji) {
    var normalized = normalizeReactionDetailEntry(entry);
    if (!normalized) return "";
    var reactedAt = normalized.reactedAt ? formatDateTime(normalized.reactedAt) : "";
    return [
      '<div class="receipt-row reaction-detail-row">',
      '  <span class="receipt-row__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(normalized.user.name)) + '</span></span>',
      '  <span class="receipt-row__copy">',
      '    <strong>' + escapeHtml(normalized.user.name) + '</strong>',
      '    <small>' + escapeHtml(reactedAt || "زمان ثبت نشده") + '</small>',
      '  </span>',
      '  <span class="receipt-row__state reaction-detail-row__emoji">' + escapeHtml(emoji) + '</span>',
      '</div>'
    ].join("");
  }

  async function openReactionDetailsModal(message, emoji) {
    var normalizedEmoji = normalizeSpace(emoji);
    if (!message || !normalizedEmoji || !reactionDetailsModal || !reactionDetailsList) return;
    var requestToken = ++state.reactionDetailsRequestToken;
    if (reactionDetailsModalTitle) {
      reactionDetailsModalTitle.textContent = "افراد واکنش‌داده به " + normalizedEmoji;
    }
    openModal(reactionDetailsModal, "reaction-details");
    reactionDetailsList.innerHTML = '<div class="chat-picker-empty">در حال دریافت فهرست واکنش‌ها...</div>';
    setModalBusy("reaction-details", true);
    try {
      var response = await apiGet("messageReactions", {
        conversationId: state.activeConversationId,
        messageId: String(message.id),
        emoji: normalizedEmoji
      });
      if (requestToken !== state.reactionDetailsRequestToken) {
        return;
      }
      ensureSuccessResponse(response, "فهرست واکنش‌ها دریافت نشد.");
      var reactions = asObject(response.reactions) || {};
      var entries = Array.isArray(reactions[normalizedEmoji]) ? reactions[normalizedEmoji].map(normalizeReactionDetailEntry).filter(Boolean) : [];
      if (!entries.length) {
        reactionDetailsList.innerHTML = '<div class="chat-picker-empty">هنوز برای این واکنش کسی ثبت نشده است.</div>';
        return;
      }
      reactionDetailsList.innerHTML = '<div class="receipt-section"><strong>' + escapeHtml(reactionCountLabel({ count: entries.length })) + '</strong>' + entries.map(function (entry) {
        return renderReactionDetailRow(entry, normalizedEmoji);
      }).join("") + '</div>';
      hydrateReceiptAvatars(reactionDetailsList, entries, 0);
    } catch (error) {
      reactionDetailsList.innerHTML = '<div class="chat-picker-empty">' + escapeHtml(error && error.message ? error.message : "فهرست واکنش‌ها دریافت نشد.") + '</div>';
    } finally {
      if (requestToken === state.reactionDetailsRequestToken) {
        setModalBusy("reaction-details", false);
      }
    }
  }

  async function openReceiptsModal(message) {
    if (!message || !receiptsModal || !receiptsList) return;
    openModal(receiptsModal, "receipts");
    receiptsList.innerHTML = '<div class="chat-picker-empty">در حال دریافت وضعیت مشاهده...</div>';
    setModalBusy("receipts", true);
    try {
      var response = await apiGet("messageReceipts", {
        conversationId: state.activeConversationId,
        messageId: String(message.id)
      });
      ensureSuccessResponse(response, "وضعیت مشاهده پیام دریافت نشد.");
      var receipts = asObject(response.receipts) || {};
      var seen = Array.isArray(receipts.seen) ? receipts.seen : [];
      var pending = Array.isArray(receipts.pending) ? receipts.pending : [];
      if (!seen.length && !pending.length) {
        receiptsList.innerHTML = '<div class="chat-picker-empty">برای این پیام وضعیت مشاهده‌ای ثبت نشده است.</div>';
        return;
      }
      receiptsList.innerHTML = [
        seen.length ? '<div class="receipt-section"><strong>مشاهده‌شده</strong>' + seen.map(function (entry) { return renderReceiptRow(entry, true); }).join("") + '</div>' : "",
        pending.length ? '<div class="receipt-section"><strong>در انتظار مشاهده</strong>' + pending.map(function (entry) { return renderReceiptRow(entry, false); }).join("") + '</div>' : ""
      ].join("");
      hydrateReceiptAvatars(receiptsList, seen, 0);
      hydrateReceiptAvatars(receiptsList, pending, seen.length);
    } catch (error) {
      receiptsList.innerHTML = '<div class="chat-picker-empty">' + escapeHtml(error && error.message ? error.message : "وضعیت مشاهده پیام دریافت نشد.") + '</div>';
    } finally {
      setModalBusy("receipts", false);
    }
  }

  function clearReplyTarget() {
    state.replyTargetId = null;
    if (replyBar) replyBar.hidden = true;
  }

  function setReplyTarget(message, options) {
    var opts = asObject(options) || {};
    state.replyTargetId = message.id;
    if (!replyBar || !replyToName || !replyToSnippet) return;
    replyBar.hidden = false;
    replyToName.textContent = message.name || "کاربر";
    replyToSnippet.textContent = snippet(messagePreviewText(message), 90);
    if (opts.focusComposer !== false && chatTextEl) {
      chatTextEl.focus();
    }
  }

  function appendMessages(messages, options) {
    var list = Array.isArray(messages) ? messages : [];
    if (!messagesEl) return;

    var shouldStick = !!state.threadAutoStick && isThreadNearBottom(56);
    var markNew = !!(options && options.markNew);
    var forceReplace = !!(options && options.replaceAll);
    var prepend = !!(options && options.prepend);
    var scrollHeightBefore = prepend ? messagesEl.scrollHeight : 0;
    var scrollTopBefore = prepend ? messagesEl.scrollTop : 0;
    var existingNodes = new Map();
    var fragment = document.createDocumentFragment();

    if (forceReplace) {
      state.messages.clear();
      invalidateMessageListCache();
      messagesEl.innerHTML = "";
      state.lastMessageId = 0;
      state.oldestMessageId = 0;
    } else {
      Array.from(messagesEl.querySelectorAll(".msg-item[data-mid]")).forEach(function (node) {
        if (!node || !node.dataset || !node.dataset.mid) return;
        existingNodes.set(node.dataset.mid, node);
      });
    }

    list.forEach(function (message) {
      if (!message || message.id <= 0) return;
      state.lastMessageId = Math.max(state.lastMessageId, message.id);
      state.messages.set(message.id, message);
      state.messageOrderDirty = true;

      var nextNode = renderMessage(message);
      bindMediaAutoStick(nextNode);
      var existing = existingNodes.get(String(message.id));
      if (existing) {
        existing.replaceWith(nextNode);
      } else {
        if (markNew) {
          nextNode.classList.add("is-new");
          window.setTimeout(function () {
            nextNode.classList.remove("is-new");
          }, 220);
        }
        fragment.appendChild(nextNode);
      }
    });

    if (fragment.childNodes.length) {
      if (prepend && messagesEl.firstChild) {
        messagesEl.insertBefore(fragment, messagesEl.firstChild);
      } else {
        messagesEl.appendChild(fragment);
      }
    }

    var ordered = messageList();
    state.oldestMessageId = ordered.length ? ordered[0].id : 0;

    updateMessageGroups();
    renderUnreadDivider();
    syncMessageFocus();
    updatePinnedUi();
    updateInfoSheet();

    if (state.messages.size === 0) {
      showStreamState("empty", "این گفت‌وگو هنوز خالی است", "برای شروع، یک پیام جدید ارسال کن.");
    } else {
      showStreamState("", "", "");
    }

    if (shouldStick || (options && options.forceStick)) {
      state.threadAutoStick = true;
      window.requestAnimationFrame(function () {
        scrollToBottom(!!(options && options.smooth));
      });
    } else if (prepend) {
      window.requestAnimationFrame(function () {
        var delta = messagesEl.scrollHeight - scrollHeightBefore;
        messagesEl.scrollTop = scrollTopBefore + Math.max(0, delta);
      });
    }

    scheduleFastChatCacheSave(prepend ? 320 : 140);
  }

  function maybeLoadOlderMessages() {
    if (!messagesEl || state.olderMessagesLoading || !state.hasMoreBefore || !state.activeConversationId) return;
    if (messagesEl.scrollTop > 96) return;
    var oldest = Math.max(0, Math.floor(toNumber(state.oldestMessageId, 0)));
    if (!oldest) {
      var ordered = messageList();
      oldest = ordered.length ? Math.max(0, Math.floor(toNumber(ordered[0].id, 0))) : 0;
      state.oldestMessageId = oldest;
    }
    if (oldest <= 1) {
      state.hasMoreBefore = false;
      return;
    }

    state.olderMessagesLoading = true;
    syncConversation({
      forceFull: true,
      beforeId: oldest,
      messageLimit: OLDER_MESSAGE_LIMIT,
      includeMembers: false,
      conversationId: state.activeConversationId,
      silent: true
    }).catch(function () {}).finally(function () {
      state.olderMessagesLoading = false;
    });
  }

  function replaceMessageInDom(message) {
    if (!message || !messagesEl) return;
    state.messages.set(message.id, message);
    invalidateMessageListCache();
    var existing = messagesEl.querySelector('[data-mid="' + message.id + '"]');
    if (existing) {
      var nextNode = renderMessage(message);
      bindMediaAutoStick(nextNode);
      existing.replaceWith(nextNode);
    }
    updateMessageGroups();
    renderUnreadDivider();
    syncMessageFocus();
    updatePinnedUi();
    updateInfoSheet();
  }

  function removeMessageFromDom(messageId) {
    if (!messagesEl) return;
    var numericId = Number(messageId);
    state.messages.delete(numericId);
    invalidateMessageListCache();
    var existing = messagesEl.querySelector('[data-mid="' + numericId + '"]');
    if (existing) existing.remove();
    updateMessageGroups();
    renderUnreadDivider();
    updatePinnedUi();
    updateInfoSheet();
    if (state.messages.size === 0) {
      showStreamState("empty", "این گفت‌وگو هنوز خالی است", "برای شروع، یک پیام جدید ارسال کن.");
    }
  }

  var CONTEXT_ACTION_ICONS = {
    "پاسخ": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><path d="M9 7 4 12l5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 12h11a5 5 0 0 1 5 5v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "فوروارد": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><path d="M15 7l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 12H9a5 5 0 0 0-5 5v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "کپی": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><rect x="9" y="9" width="11" height="11" rx="2.2" stroke="currentColor" stroke-width="1.8"/><path d="M5 15V5.2A1.2 1.2 0 0 1 6.2 4H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "انتخاب چندتایی": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><rect x="4" y="4" width="16" height="16" rx="4" stroke="currentColor" stroke-width="1.8"/><path d="M8 12l3 3 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "واکنش دیگر": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/><path d="M9 10h.01M15 10h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><path d="M8.5 14.5c.9 1.2 2.1 1.8 3.5 1.8s2.6-.6 3.5-1.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    "ویرایش": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><path d="M4 20l.9-3.6L15.6 5.7a1.5 1.5 0 0 1 2.1 0l1.6 1.6a1.5 1.5 0 0 1 0 2.1L8.6 20.1 4 20Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14.3 7.3l2.4 2.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    "سنجاق کردن": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><path d="M12 2.5v8.5M8 11h8M10 11v8l2-1 2 1v-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "برداشتن سنجاق": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><path d="M12 2.5v8.5M8 11h8M10 11v8l2-1 2 1v-8M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "حذف": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><path d="M5 7h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7" stroke="currentColor" stroke-width="1.8"/><path d="M7 7l.6 11.2A2 2 0 0 0 9.6 20h4.8a2 2 0 0 0 2-1.8L17 7" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 11v5M14 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    "فهرست سین‌ها": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><path d="M2.5 12S6 6 12 6s9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>',
    "_default": '<svg viewBox="0 0 24 24" fill="none" width="19" height="19"><circle cx="12" cy="12" r="2.2" fill="currentColor"/></svg>'
  };

  function contextAction(label, hint, onClick, className) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = "chat-context-action" + (className ? " " + className : "");
    if (hint) button.title = hint;
    var icon = CONTEXT_ACTION_ICONS[label] || CONTEXT_ACTION_ICONS._default;
    button.innerHTML =
      '<span class="chat-context-action__icon" aria-hidden="true">' + icon + "</span>" +
      '<span class="chat-context-action__label">' + escapeHtml(label) + "</span>";
    button.addEventListener("click", onClick);
    return button;
  }

  function positionContextMenu(clientX, clientY) {
    if (!contextMenu) return;
    var menuWidth = contextMenu.offsetWidth;
    var menuHeight = contextMenu.offsetHeight;
    var left = Math.max(8, Math.min(clientX - menuWidth / 2, window.innerWidth - menuWidth - 8));
    var top;
    if (isMobileViewport()) {
      var preferredTop = clientY - menuHeight - 10;
      top = preferredTop >= 8 ? preferredTop : clientY + 10;
      top = Math.max(8, Math.min(top, window.innerHeight - menuHeight - 8));
    } else {
      top = Math.max(8, Math.min(clientY + 12, window.innerHeight - menuHeight - 8));
    }
    contextMenu.style.setProperty("left", left + "px", "important");
    contextMenu.style.setProperty("top", top + "px", "important");
  }

  function closeContextMenu() {
    if (!state.contextOpen) return;
    state.contextOpen = false;
    state.contextAnchorMessageId = null;
    syncMessageFocus();
    if (contextBackdrop) {
      contextBackdrop.classList.remove("is-open");
      contextBackdrop.hidden = true;
    }
    if (contextMenu) {
      contextMenu.classList.remove("is-open");
      contextMenu.hidden = true;
      contextMenu.style.left = "";
      contextMenu.style.top = "";
    }
    updateMobileNav();
  }

  var LIST_CTX_ICONS = {
    "باز کردن گفتگو": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "اطلاعات گفتگو": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    "انتخاب": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><rect x="4" y="4" width="16" height="16" rx="4" stroke="currentColor" stroke-width="1.8"/><path d="M8 12l3 3 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "برداشتن از انتخاب": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><rect x="4" y="4" width="16" height="16" rx="4" stroke="currentColor" stroke-width="1.8"/><path d="M9 9l6 6M15 9l-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    "سنجاق کردن": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M12 2v9M8 11h8M10 11v8l2-1 2 1v-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "برداشتن سنجاق": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M12 2v9M8 11h8M10 11v8l2-1 2 1v-8M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "بی‌صدا": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M12 4a4 4 0 0 0-4 4v3c0 .5-.2 1-.5 1.4L6 14c-.5.6-.1 1.5.7 1.5h10.6c.8 0 1.2-.9.7-1.5l-1.5-1.6A2 2 0 0 1 16 11V8a4 4 0 0 0-4-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M10 18.5a2 2 0 0 0 4 0M3 3l18 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    "خارج کردن از سکوت": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M12 4a4 4 0 0 0-4 4v3c0 .5-.2 1-.5 1.4L6 14c-.5.6-.1 1.5.7 1.5h10.6c.8 0 1.2-.9.7-1.5l-1.5-1.6A2 2 0 0 1 16 11V8a4 4 0 0 0-4-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M10 18.5a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    "علامت‌گذاری به‌عنوان خوانده‌شده": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M4 13l4.5 4.5L20 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "علامت‌گذاری به‌عنوان خوانده‌نشده": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>',
    "بایگانی": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><rect x="3" y="4" width="18" height="4" rx="1.5" stroke="currentColor" stroke-width="1.8"/><path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8" stroke="currentColor" stroke-width="1.8"/><path d="M10 12h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    "خروج از بایگانی": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><rect x="3" y="4" width="18" height="4" rx="1.5" stroke="currentColor" stroke-width="1.8"/><path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8" stroke="currentColor" stroke-width="1.8"/><path d="M12 12v4M10 14l2-2 2 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "حذف گفتگو": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "حذف پیام": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    "ارسال پیام خصوصی": '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
  };

  function listContextAction(label, hint, onClick, className) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = "chat-list-context-action" + (className ? " " + className : "");
    var icon = LIST_CTX_ICONS[label] || "";
    button.innerHTML =
      (icon ? '<span class="chat-list-context-action__icon" aria-hidden="true">' + icon + "</span>" : "") +
      '<span class="chat-list-context-action__label">' + escapeHtml(label) + "</span>";
    button.addEventListener("click", function () {
      Promise.resolve(onClick && onClick()).catch(function (error) {
        showToast((error && error.message) || "اجرای عملیات انجام نشد.");
      });
    });
    return button;
  }

  function positionListContextMenu(clientX, clientY) {
    if (!listContextMenu) return;
    var menuWidth = listContextMenu.offsetWidth;
    var menuHeight = listContextMenu.offsetHeight;
    var left = Math.max(8, Math.min(clientX - menuWidth / 2, window.innerWidth - menuWidth - 8));
    var top;
    if (isMobileViewport()) {
      var preferredTop = clientY - menuHeight - 10;
      top = preferredTop >= 8 ? preferredTop : clientY + 10;
      top = Math.max(8, Math.min(top, window.innerHeight - menuHeight - 8));
    } else {
      top = Math.max(8, Math.min(clientY + 12, window.innerHeight - menuHeight - 8));
    }
    listContextMenu.style.setProperty("left", left + "px", "important");
    listContextMenu.style.setProperty("top", top + "px", "important");
  }

  function closeListContextMenu() {
    if (!state.listContextOpen) return;
    state.listContextOpen = false;
    state.listContextConversationId = "";
    if (listContextBackdrop) {
      listContextBackdrop.classList.remove("is-open");
      listContextBackdrop.hidden = true;
    }
    if (listContextMenu) {
      listContextMenu.classList.remove("is-open");
      listContextMenu.hidden = true;
      listContextMenu.style.left = "";
      listContextMenu.style.top = "";
    }
    updateMobileNav();
  }

  function openListContextMenu(conversation, clientX, clientY) {
    if (!conversation || !listContextBackdrop || !listContextMenu || !listContextActions) return;
    closeContextMenu();
    state.listContextOpen = true;
    state.listContextConversationId = conversation.id;
    if (listContextTitle) listContextTitle.textContent = conversation.title || "گفت‌وگو";
    if (listContextSubtitle) {
      var parts = [];
      parts.push(conversationBadgeText(conversation));
      if (conversation.viewerState && conversation.viewerState.archived) {
        parts.push("بایگانی");
      }
      if (conversation.unreadCount > 0) {
        parts.push(conversation.unreadCount.toLocaleString("fa-IR") + " خوانده‌نشده");
      }
      listContextSubtitle.textContent = parts.filter(Boolean).join(" • ");
    }

    var runAndClose = function (fn) {
      return function () {
        closeListContextMenu();
        return fn();
      };
    };

    var isPinned = !!(conversation.viewerState && conversation.viewerState.pinned);
    var isMuted = !!(conversation.settings && conversation.settings.muted);
    var isArchived = !!(conversation.viewerState && conversation.viewerState.archived);
    var hasUnread = Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0))) > 0;
    var isSelected = state.selectedConversationIds.has(conversation.id);

    listContextActions.innerHTML = "";
    listContextActions.appendChild(listContextAction("باز کردن گفتگو", "نمایش رشته پیام", runAndClose(function () {
      return openConversation(conversation.id, { forceFull: true, source: "list-context" });
    })));
    listContextActions.appendChild(listContextAction("اطلاعات گفتگو", "مشاهده پروفایل و تنظیمات", runAndClose(async function () {
      await openConversation(conversation.id, { forceFull: true, source: "list-context-info" });
      await openInfoSheet();
    })));
    listContextActions.appendChild(listContextAction(
      isSelected ? "برداشتن از انتخاب" : "انتخاب",
      "افزودن به حالت چندانتخابی",
      runAndClose(function () {
        if (!state.listSelectionMode) {
          enterConversationSelectionMode(conversation.id);
          return;
        }
        toggleConversationSelection(conversation.id, !isSelected);
      })
    ));

    if (conversation.permissions && conversation.permissions.canPinConversation) {
      listContextActions.appendChild(listContextAction(
        isPinned ? "برداشتن سنجاق" : "سنجاق کردن",
        "مدیریت جایگاه در لیست",
        runAndClose(function () {
          return setConversationPinById(conversation.id, !isPinned);
        })
      ));
    }

    if (conversation.permissions && conversation.permissions.canMuteConversation) {
      listContextActions.appendChild(listContextAction(
        isMuted ? "خارج کردن از سکوت" : "بی‌صدا",
        "مدیریت اعلان و ارسال",
        runAndClose(function () {
          return setConversationMuteById(conversation.id, !isMuted);
        })
      ));
    }

    if (conversation.permissions && (conversation.permissions.canMarkRead || conversation.permissions.canMarkUnread)) {
      listContextActions.appendChild(listContextAction(
        hasUnread ? "علامت‌گذاری به‌عنوان خوانده‌شده" : "علامت‌گذاری به‌عنوان خوانده‌نشده",
        "وضعیت خوانده‌نشده",
        runAndClose(function () {
          return setConversationReadStateById(conversation.id, !hasUnread);
        })
      ));
    }

    if (conversation.permissions && conversation.permissions.canArchiveConversation) {
      listContextActions.appendChild(listContextAction(
        isArchived ? "خروج از بایگانی" : "بایگانی",
        "سازمان‌دهی لیست گفتگو",
        runAndClose(function () {
          return setConversationArchiveById(conversation.id, !isArchived);
        })
      ));
    }

    if (conversation.permissions && conversation.permissions.canDeleteConversation) {
      listContextActions.appendChild(listContextAction(
        "حذف گفتگو",
        "حذف از لیست گفتگوها",
        runAndClose(function () {
          return deleteConversationById(conversation.id);
        }),
        "is-danger"
      ));
    }

    listContextBackdrop.hidden = false;
    listContextMenu.hidden = false;
    window.requestAnimationFrame(function () {
      listContextBackdrop.classList.add("is-open");
      listContextMenu.classList.add("is-open");
      positionListContextMenu(clientX, clientY);
    });
    updateMobileNav();
  }

  function openMemberContextMenu(member, clientX, clientY) {
    if (!member || !listContextBackdrop || !listContextMenu || !listContextActions) return;
    closeContextMenu();
    state.listContextOpen = true;
    state.listContextConversationId = "";
    if (listContextTitle) listContextTitle.textContent = member.name || "عضو";
    if (listContextSubtitle) listContextSubtitle.textContent = userRoleMetaText(member);

    var runAndClose = function (fn) {
      return function () {
        closeListContextMenu();
        return fn();
      };
    };

    listContextActions.innerHTML = "";
    listContextActions.appendChild(listContextAction("تگ عضو", "نمایش کنار نام در گروه", runAndClose(function () {
      return setMemberTag(member.studentNumber);
    })));
    listContextActions.appendChild(listContextAction(
      member.isConversationAdmin ? "حذف مدیر" : "مدیر کردن",
      member.isConversationAdmin ? "برداشتن دسترسی مدیریت" : "افزودن دسترسی مدیریت",
      runAndClose(function () {
        return setMemberAdmin(member.studentNumber, !member.isConversationAdmin);
      })
    ));

    listContextBackdrop.hidden = false;
    listContextMenu.hidden = false;
    window.requestAnimationFrame(function () {
      listContextBackdrop.classList.add("is-open");
      listContextMenu.classList.add("is-open");
      positionListContextMenu(clientX, clientY);
    });
    updateMobileNav();
  }

  function closePeerSheet() {
    if (!peerSheet) return;
    peerSheet.classList.remove("is-open");
    peerSheet.hidden = true;
    if (infoSheetBackdrop && !state.infoSheetOpen) {
      infoSheetBackdrop.classList.remove("is-open");
      infoSheetBackdrop.hidden = true;
    }
    state.peerSheetOpen = false;
    refreshTransportBinding();
    updateMobileNav();
  }

  function openChatMemberProfile(member) {
    if (!member || !peerSheet) return;
    if (state.infoSheetOpen) closeInfoSheet();
    closeListContextMenu();
    closeContextMenu();

    renderAvatar(peerAvatar, peerAvatarImg, peerAvatarFallback,
      member.avatarUrl || (member.profile && member.profile.avatarUrl), member.name);

    if (peerName) peerName.textContent = member.name || "کاربر";
    if (peerStatus) peerStatus.textContent = userRoleMetaText(member);

    var about = normalizeSpace(member.about || (member.profile && member.profile.about) || "");
    if (peerAbout) {
      peerAbout.textContent = about;
      peerAbout.hidden = !about;
    }

    if (peerQuickActions) {
      peerQuickActions.innerHTML = "";
      var isOwnProfile = normalizeStudentNumber(member.studentNumber) === normalizeStudentNumber(state.me && state.me.studentNumber);
      if (!isOwnProfile && member.studentNumber) {
        var dmBtn = document.createElement("button");
        dmBtn.type = "button";
        dmBtn.className = "chat-info-quick-action";
        dmBtn.innerHTML = '<span class="chat-info-quick-action__icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></span><span class="chat-info-quick-action__label">پیام خصوصی</span>';
        dmBtn.addEventListener("click", function () {
          closePeerSheet();
          startDirectConversation(member.studentNumber);
        });
        peerQuickActions.appendChild(dmBtn);
      }
      peerQuickActions.hidden = peerQuickActions.children.length === 0;
    }

    var rows = [];
    if (canCurrentUserViewStudentNumbers() && member.studentNumber) {
      rows.push({ label: "شماره دانشجویی", value: member.studentNumber });
    }
    var roleLabel = normalizeSpace(member.roleLabel) || "دانشجو";
    rows.push({ label: "نقش", value: roleLabel });
    if (peerInfoRows) renderInfoRows(peerInfoRows, rows);
    if (peerInfoBlock) peerInfoBlock.hidden = false;

    state.peerSheetOpen = true;
    peerSheet.hidden = false;
    var mobileSheet = isMobileViewport();
    if (infoSheetBackdrop) {
      infoSheetBackdrop.hidden = !mobileSheet;
    }
    window.requestAnimationFrame(function () {
      peerSheet.classList.add("is-open");
      if (infoSheetBackdrop && !infoSheetBackdrop.hidden) {
        infoSheetBackdrop.classList.add("is-open");
      }
    });
    refreshTransportBinding();
    updateMobileNav();
  }

  function openContextMenu(message, clientX, clientY) {
    if (!message || !contextBackdrop || !contextMenu || !reactionBar || !contextActions) return;
    if (state.messageSelectionMode) {
      toggleMessageSelection(message.id);
      return;
    }
    closeListContextMenu();
    state.contextAnchorMessageId = message.id;
    syncMessageFocus();

    reactionBar.innerHTML = "";
    var ownReactions = new Set(reactionEntries(message).filter(function (entry) { return entry.own; }).map(function (entry) { return entry.emoji; }));
    var quickReactionItems = quickReactionsForMessage(message);
    quickReactionItems.forEach(function (emoji) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "chat-reaction-btn";
      if (ownReactions.has(emoji)) {
        button.classList.add("is-active");
      }
      button.textContent = emoji;
      button.addEventListener("click", function () {
        toggleReaction(message, emoji);
      });
      reactionBar.appendChild(button);
    });
    if (reactionAllowedInActiveConversation(QUICK_REACTIONS[0])) {
      var customReactionBtn = document.createElement("button");
      customReactionBtn.type = "button";
      customReactionBtn.className = "chat-reaction-btn chat-reaction-btn-more";
      customReactionBtn.textContent = "+";
      customReactionBtn.title = "واکنش دیگر";
      customReactionBtn.addEventListener("click", function () {
        openReactionPicker(message);
      });
      reactionBar.appendChild(customReactionBtn);
    }

    contextActions.innerHTML = "";
    var receiptSummary = messageReceiptSummary(message);
    if (receiptSummary) {
      var receiptNode = document.createElement("div");
      receiptNode.className = "chat-context-receipt";
      receiptNode.textContent = receiptSummary;
      contextActions.appendChild(receiptNode);
    }
    if (message.studentNumber === state.me.studentNumber) {
      var conversation = activeConversation();
      if (conversation && conversation.type !== "direct" && Math.max(0, Math.floor(toNumber(conversation.memberCount, 0))) > 2) {
        contextActions.appendChild(contextAction("فهرست سین‌ها", "مشاهده اعضای دیده یا ندیده", function () {
          openReceiptsModal(message);
          closeContextMenu();
        }));
      }
    }
    contextActions.appendChild(contextAction("پاسخ", "پاسخ به پیام", function () {
      setReplyTarget(message);
      closeContextMenu();
    }));

    contextActions.appendChild(contextAction("فوروارد", "ارسال به گفتگوی دیگر", function () {
      openForwardPicker(message);
      closeContextMenu();
    }));

    contextActions.appendChild(contextAction("کپی", "کپی متن", function () {
      copyMessageText(message);
      closeContextMenu();
    }));

    contextActions.appendChild(contextAction("انتخاب چندتایی", "افزودن پیام به حالت چندانتخابی", function () {
      enterMessageSelectionMode(message.id);
      closeContextMenu();
    }));

    if (quickReactionItems.length) {
      contextActions.appendChild(contextAction("واکنش دیگر", "ثبت هر ایموجی", function () {
        openReactionPicker(message);
        closeContextMenu();
      }));
    }

    if (canManageMessage(message)) {
      contextActions.appendChild(contextAction("ویرایش", "ویرایش پیام", function () {
        openEditPrompt(message);
        closeContextMenu();
      }));
    }

    if (canPinMessage()) {
      contextActions.appendChild(contextAction(message.pinned ? "برداشتن سنجاق" : "سنجاق کردن", "تغییر وضعیت سنجاق", function () {
        togglePin(message, !message.pinned);
        closeContextMenu();
      }));
    }

    if (canManageMessage(message)) {
      contextActions.appendChild(contextAction("حذف", "حذف پیام", function () {
        deleteMessage(message);
        closeContextMenu();
      }, "is-danger"));
    }

    contextBackdrop.hidden = false;
    contextMenu.hidden = false;
    window.requestAnimationFrame(function () {
      contextBackdrop.classList.add("is-open");
      contextMenu.classList.add("is-open");
      positionContextMenu(clientX, clientY);
    });
    state.contextOpen = true;
    updateMobileNav();
  }

  function promptCustomReaction(message) {
    if (!message) return;
    if (reactionModal) {
      openReactionPicker(message);
      return;
    }
    var value = window.prompt("ایموجی واکنش را وارد کن", "");
    if (value === null) return;
    var emoji = normalizeSpace(value);
    if (!isLikelyEmoji(emoji)) {
      showToast("ایموجی معتبر وارد کن.");
      return;
    }
    toggleReaction(message, emoji);
  }

  function attachBubbleMenuEvents(bubble, message) {
    if (!bubble || !message) return;

    var touchTimer = null;
    var startX = 0;
    var startY = 0;
    var cancelled = false;
    var gesturePointerId = null;
    var swipeTracking = false;
    var swipeReady = false;
    var startInteractive = false;
    var lastTapAt = 0;
    var lastTapX = 0;
    var lastTapY = 0;
    var lastGestureHeartAt = 0;

    function clearTouchTimer() {
      if (touchTimer) {
        window.clearTimeout(touchTimer);
        touchTimer = null;
      }
    }

    function fireGestureHeart(event) {
      var now = Date.now();
      if ((now - lastGestureHeartAt) < 320) return;
      lastGestureHeartAt = now;
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      triggerGestureHeartReaction(message);
    }

    function resetSwipe(options) {
      if (!swipeTracking && !bubble.classList.contains("is-swipe-tracking") && !bubble.classList.contains("is-swipe-settling")) {
        return;
      }
      clearBubbleSwipeState(bubble, options);
      swipeTracking = false;
      swipeReady = false;
    }

    bubble.addEventListener("contextmenu", function (event) {
      event.preventDefault();
      if (state.messageSelectionMode) {
        toggleMessageSelection(message.id);
        return;
      }
      openContextMenu(message, event.clientX, event.clientY);
    });

    bubble.addEventListener("click", function (event) {
      if (!state.messageSelectionMode || interactiveMessageTarget(event.target)) return;
      event.preventDefault();
      event.stopPropagation();
      toggleMessageSelection(message.id);
    });

    bubble.addEventListener("pointerdown", function (event) {
      if (state.messageSelectionMode) {
        clearTouchTimer();
        gesturePointerId = null;
        return;
      }
      if (!touchLikePointer(event.pointerType)) return;
      if (event.button != null && event.button !== 0) return;
      startInteractive = interactiveMessageTarget(event.target);
      if (startInteractive) {
        gesturePointerId = null;
        clearTouchTimer();
        return;
      }
      gesturePointerId = event.pointerId;
      if (typeof bubble.setPointerCapture === "function") {
        try {
          bubble.setPointerCapture(event.pointerId);
        } catch (_error) {}
      }
      startX = event.clientX;
      startY = event.clientY;
      cancelled = false;
      swipeTracking = false;
      swipeReady = false;
      clearTouchTimer();
      touchTimer = window.setTimeout(function () {
        if (cancelled) return;
        openContextMenu(message, event.clientX, event.clientY);
      }, 460);
    });

    bubble.addEventListener("pointermove", function (event) {
      if (state.messageSelectionMode) return;
      if (!touchLikePointer(event.pointerType) || gesturePointerId !== event.pointerId || startInteractive) return;
      var deltaX = event.clientX - startX;
      var deltaY = event.clientY - startY;
      if (!swipeTracking && touchTimer && (Math.abs(deltaX) > 9 || Math.abs(deltaY) > 9)) {
        cancelled = true;
      }
      if (!swipeTracking && deltaX > 12 && deltaX > Math.abs(deltaY) * 1.15) {
        swipeTracking = true;
        clearTouchTimer();
      }
      if (!swipeTracking) {
        if (touchTimer && (Math.abs(deltaX) > 9 || Math.abs(deltaY) > 9)) {
          clearTouchTimer();
        }
        return;
      }
      if (deltaX <= 0) {
        setBubbleSwipeState(bubble, 0, false);
        return;
      }
      event.preventDefault();
      var offset = clamp(deltaX * 0.94, 0, messageReplySwipeLimitPx());
      var ready = offset >= messageReplySwipeThresholdPx();
      setBubbleSwipeState(bubble, offset, ready);
      if (ready && !swipeReady) {
        softHaptic(10);
      }
      swipeReady = ready;
    });

    bubble.addEventListener("pointerup", function (event) {
      if (state.messageSelectionMode) return;
      if (!touchLikePointer(event.pointerType) || gesturePointerId !== event.pointerId) return;
      clearTouchTimer();
      gesturePointerId = null;
      if (startInteractive) return;

      var deltaX = event.clientX - startX;
      var deltaY = event.clientY - startY;
      if (swipeTracking) {
        var shouldReply = deltaX >= messageReplySwipeThresholdPx() && deltaX > Math.abs(deltaY);
        resetSwipe({ acknowledge: shouldReply });
        if (shouldReply) {
          setReplyTarget(message, { focusComposer: false });
          softHaptic(motionEffectsEnabled() ? [12, 42, 12] : 8);
          event.preventDefault();
          event.stopPropagation();
        }
        if (typeof bubble.releasePointerCapture === "function") {
          try {
            bubble.releasePointerCapture(event.pointerId);
          } catch (_error) {}
        }
        return;
      }

      if (cancelled || Math.abs(deltaX) > 8 || Math.abs(deltaY) > 8) {
        return;
      }

      var now = Date.now();
      var isDoubleTap = (now - lastTapAt) <= MESSAGE_DOUBLE_TAP_WINDOW_MS
        && Math.abs(event.clientX - lastTapX) <= MESSAGE_DOUBLE_TAP_MOVE_PX
        && Math.abs(event.clientY - lastTapY) <= MESSAGE_DOUBLE_TAP_MOVE_PX;

      lastTapAt = now;
      lastTapX = event.clientX;
      lastTapY = event.clientY;

      if (isDoubleTap) {
        lastTapAt = 0;
        fireGestureHeart(event);
      }
      if (typeof bubble.releasePointerCapture === "function") {
        try {
          bubble.releasePointerCapture(event.pointerId);
        } catch (_error) {}
      }
    });

    ["pointercancel"].forEach(function (eventName) {
      bubble.addEventListener(eventName, function (event) {
        if (state.messageSelectionMode) return;
        clearTouchTimer();
        gesturePointerId = null;
        if (swipeTracking) {
          resetSwipe({ acknowledge: false });
        }
        if (event && typeof bubble.releasePointerCapture === "function" && event.pointerId != null) {
          try {
            bubble.releasePointerCapture(event.pointerId);
          } catch (_error) {}
        }
      });
    });

    bubble.addEventListener("dblclick", function (event) {
      if (state.messageSelectionMode) return;
      if (interactiveMessageTarget(event.target)) return;
      fireGestureHeart(event);
    });
  }

  async function copyTextToClipboard(text) {
    var value = toText(text);
    if (!normalizeSpace(value)) return false;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
      } else {
        var helper = document.createElement("textarea");
        helper.value = value;
        helper.setAttribute("readonly", "");
        helper.style.position = "fixed";
        helper.style.opacity = "0";
        helper.style.pointerEvents = "none";
        document.body.appendChild(helper);
        helper.focus();
        helper.select();
        document.execCommand("copy");
        helper.remove();
      }
      return true;
    } catch (error) {
      return false;
    }
  }

  function messageExportText(message, options) {
    if (!message) return "";
    var opts = asObject(options) || {};
    var senderName = normalizeSpace(message.name) || "کاربر";
    var previewText = normalizeSpace(messagePreviewText(message)) || "پیام بدون متن";
    if (opts.forwardStyle) {
      return "↪️ فوروارد از " + senderName + ":\n" + previewText;
    }
    return senderName + ":\n" + previewText;
  }

  async function copyMessageText(message) {
    var text = messageExportText(message, { forwardStyle: false });
    if (!normalizeSpace(text)) return;
    if (await copyTextToClipboard(text)) {
      showToast("پیام کپی شد.");
    } else {
      showToast("کپی پیام انجام نشد.");
    }
  }

  async function copySelectedMessagesText() {
    var messages = selectedMessages();
    if (!messages.length) {
      showToast("هیچ پیامی انتخاب نشده است.");
      return;
    }
    var text = messages.map(function (message) {
      return messageExportText(message, { forwardStyle: false });
    }).join("\n\n");
    if (await copyTextToClipboard(text)) {
      showToast("پیام‌های انتخاب‌شده کپی شدند.");
      exitMessageSelectionMode();
    } else {
      showToast("کپی پیام‌ها انجام نشد.");
    }
  }

  async function runMessageBatchAction(actionKey) {
    var action = normalizeSpace(actionKey).toLowerCase();
    var conversation = activeConversation();
    var messages = selectedMessages();
    if (!conversation) {
      showToast("گفت‌وگویی انتخاب نشده است.");
      return;
    }
    if (!messages.length) {
      showToast("هیچ پیامی انتخاب نشده است.");
      return;
    }

    if (action === "copy") {
      await copySelectedMessagesText();
      return;
    }

    if (action === "forward") {
      openForwardPickerForMessages(messages.map(function (message) {
        return message.id;
      }));
      return;
    }

    if (action !== "pin" && action !== "unpin" && action !== "delete") {
      return;
    }

    if ((action === "pin" || action === "unpin") && !canPinMessage()) {
      showToast("اجازه سنجاق این گفت‌وگو را نداری.");
      return;
    }

    if (action === "delete") {
      var approved = await openConfirmDialog({
        title: "حذف پیام‌های انتخاب‌شده",
        message: "پیام‌های قابل‌مدیریت حذف می‌شوند و بقیه دست‌نخورده می‌مانند.",
        acceptLabel: "حذف پیام‌ها",
        danger: true
      });
      if (!approved) return;
    }

    closeContextMenu();

    var ids = messages.map(function (message) {
      return message.id;
    });

    try {
      var response = null;
      if (action === "pin" || action === "unpin") {
        response = await apiPost("batchPinMessages", {
          conversationId: conversation.id,
          ids: ids.join(","),
          pinned: action === "pin" ? "1" : "0"
        });
      } else {
        response = await apiPost("batchDeleteMessages", {
          conversationId: conversation.id,
          ids: ids.join(",")
        });
      }

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, action === "delete" ? "حذف گروهی پیام انجام نشد." : "سنجاق گروهی پیام انجام نشد.");

      await refreshAfterConversationAction(response, conversation.id, {
        keepCurrentActive: true,
        forceFull: false,
        skipSync: true
      });

      var success = 0;
      var skipped = Array.isArray(response && response.skippedIds) ? response.skippedIds.length : 0;
      if (action === "delete") {
        var deletedIds = Array.isArray(response && response.deletedIds) ? response.deletedIds : [];
        deletedIds.forEach(function (messageId) {
          removeMessageFromDom(messageId);
        });
        success = deletedIds.length;
      } else {
        var updatedMessages = (Array.isArray(response && response.messages) ? response.messages : [])
          .map(normalizeMessage)
          .filter(Boolean);
        updatedMessages.forEach(function (message) {
          replaceMessageInDom(message);
        });
        success = updatedMessages.length;
      }

      if (success > 0) {
        exitMessageSelectionMode();
      } else {
        updateMessageSelectionUi();
      }

      var successLabel = "";
      if (action === "pin") {
        successLabel = success > 1 ? "پیام‌ها سنجاق شدند." : "پیام سنجاق شد.";
      } else if (action === "unpin") {
        successLabel = success > 1 ? "سنجاق پیام‌ها برداشته شد." : "سنجاق پیام برداشته شد.";
      } else {
        successLabel = success > 1 ? "پیام‌ها حذف شدند." : "پیام حذف شد.";
      }

      if (success > 0 && skipped > 0) {
        showToast(successLabel + " • " + skipped.toLocaleString("fa-IR") + " مورد رد شد.");
      } else if (success > 0) {
        showToast(successLabel);
      } else if (skipped > 0) {
        showToast(skipped.toLocaleString("fa-IR") + " مورد قابل انجام نبود.");
      } else {
        showToast("تغییری اعمال نشد.");
      }
    } catch (error) {
      showToast(error && error.message
        ? error.message
        : (action === "delete" ? "حذف گروهی پیام انجام نشد." : "عملیات روی پیام‌های انتخاب‌شده انجام نشد."));
    }
  }

  async function copyConversationLink() {
    var conversation = activeConversation();
    if (!conversation) return;
    var path = conversation.shareUrl || (chatHomePath + (chatHomePath.indexOf("?") === -1 ? "?" : "&") + "conversationId=" + encodeURIComponent(conversation.id));
    var link = window.location.origin + path;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(link);
      } else {
        var helper = document.createElement("textarea");
        helper.value = link;
        helper.setAttribute("readonly", "");
        helper.style.position = "fixed";
        helper.style.opacity = "0";
        helper.style.pointerEvents = "none";
        document.body.appendChild(helper);
        helper.focus();
        helper.select();
        document.execCommand("copy");
        helper.remove();
      }
      showToast("لینک گفتگو کپی شد.");
    } catch (error) {
      showToast("کپی لینک گفتگو انجام نشد.");
    }
  }

  function renderInfoRows(container, rows) {
    if (!container) return;
    var list = Array.isArray(rows) ? rows.filter(Boolean) : [];
    if (!list.length) {
      container.innerHTML = '<div class="chat-info-row chat-info-row--empty"><span>اطلاعاتی برای نمایش وجود ندارد.</span></div>';
      return;
    }
    container.innerHTML = list.map(function (row) {
      var toneClass = row.tone ? (" chat-info-row__value--" + row.tone) : "";
      return [
        '<div class="chat-info-row">',
        '  <span class="chat-info-row__label">' + escapeHtml(row.label || "") + "</span>",
        '  <span class="chat-info-row__value' + toneClass + '">' + escapeHtml(row.value || "") + "</span>",
        "</div>"
      ].join("");
    }).join("");
  }

  function attachmentBucket(attachment) {
    var category = normalizeAttachmentCategory(attachment && attachment.category);
    if (category === "image" || category === "video") return "media";
    if (category === "audio" || category === "voice") return "voice";
    if (category === "pdf" || category === "office" || category === "document" || category === "archive" || category === "file") return "files";
    return "files";
  }

  function collectConversationContent() {
    var buckets = {
      media: [],
      files: [],
      links: [],
      voice: []
    };
    messageList().forEach(function (message) {
      (Array.isArray(message.attachments) ? message.attachments : []).forEach(function (attachment) {
        var bucket = attachmentBucket(attachment);
        buckets[bucket].push({
          message: message,
          attachment: attachment,
          title: attachment.name || attachmentCategoryLabel(attachment.category),
          meta: attachmentMetaText(attachment),
          ts: message.ts
        });
      });
      messageLinks(message.text).forEach(function (url) {
        var host = url;
        try {
          host = new URL(url).host.replace(/^www\./i, "");
        } catch (error) {}
        buckets.links.push({
          message: message,
          url: url,
          title: host,
          meta: snippet(url, 72),
          ts: message.ts
        });
      });
    });
    Object.keys(buckets).forEach(function (key) {
      buckets[key].sort(function (left, right) {
        return toNumber(right.ts, 0) - toNumber(left.ts, 0);
      });
    });
    return buckets;
  }

  function renderInfoContentOverview() {
    if (!infoContentTabs || !infoContentTable) return;
    var buckets = collectConversationContent();
    var labels = [
      { key: "media", label: "رسانه" },
      { key: "files", label: "فایل" },
      { key: "links", label: "لینک" },
      { key: "voice", label: "صوت" }
    ];
    if (!labels.some(function (item) { return item.key === state.infoContentCategory; })) {
      state.infoContentCategory = "media";
    }
    infoContentTabs.innerHTML = labels.map(function (item) {
      var count = buckets[item.key].length;
      return '<button type="button" class="' + (item.key === state.infoContentCategory ? "is-active" : "") + '" data-info-content="' + item.key + '">' + escapeHtml(item.label) + '<span>' + count.toLocaleString("fa-IR") + '</span></button>';
    }).join("");

    var activeItems = buckets[state.infoContentCategory] || [];
    infoContentTable.classList.toggle("chat-info-content-table--grid", state.infoContentCategory === "media");

    if (!activeItems.length) {
      infoContentTable.innerHTML = '<div class="chat-info-empty">موردی برای این دسته وجود ندارد.</div>';
      return;
    }

    if (state.infoContentCategory === "media") {
      infoContentTable.innerHTML = activeItems.slice(0, 60).map(function (item) {
        var media = renderAttachmentMediaButton(item.attachment, { caption: item.title || "رسانه", messageId: item.message && item.message.id });
        if (media) return media;
        return [
          '<button type="button" class="chat-info-media-cell chat-info-media-cell--placeholder" data-scroll-message="' + String(item.message.id) + '">',
          '  <span>' + escapeHtml(item.title || "رسانه") + '</span>',
          '</button>'
        ].join("");
      }).join("");
      return;
    }

    infoContentTable.innerHTML = activeItems.slice(0, 16).map(function (item) {
      var href = item.url || (item.attachment && (item.attachment.url || item.attachment.downloadUrl)) || "";
      var tag = href ? "a" : "button";
      var attrs = href
        ? ' href="' + escapeHtml(href) + '" target="_blank" rel="noopener" data-bypass-external-warning="true"'
        : ' type="button" data-scroll-message="' + String(item.message.id) + '"';
      return [
        '<' + tag + ' class="chat-info-content-row"' + attrs + '>',
        '  <span>',
        '    <strong>' + escapeHtml(item.title || "محتوا") + '</strong>',
        '    <small>' + escapeHtml(item.meta || "") + '</small>',
        '  </span>',
        '  <em>' + escapeHtml(formatDate(item.ts)) + '</em>',
        '</' + tag + '>'
      ].join("");
    }).join("");
  }

  function conversationKindLabel(conversation) {
    if (!conversation) return "گفتگو";
    if (conversation.type === "direct") return "خصوصی";
    if (conversation.type === "saved") return "پیام‌های ذخیره‌شده";
    if (conversation.type === "class-group") return "گروه اجباری کلاس";
    return conversation.settings && conversation.settings.conversationKind === "channel" ? "کانال" : "گروه";
  }

  function groupVisibilityLabel(value) {
    return value === "public" ? "عمومی با لینک دعوت" : "خصوصی";
  }

  function reactionModeLabel(value) {
    if (value === "none") return "بدون واکنش";
    if (value === "quick") return "واکنش‌های منتخب";
    return "همه واکنش‌ها";
  }

  async function setNotificationMuteForConversation(muted) {
    var conversation = activeConversation();
    if (!conversation) return;
    try {
      var response = await apiPost("setNotificationMute", {
        conversationId: conversation.id,
        muted: muted ? "1" : "0"
      });
      ensureSuccessResponse(response, "تنظیم اعلان ذخیره نشد.");
      await refreshAfterConversationAction(response, conversation.id, { keepCurrentActive: true, forceFull: false, silent: true });
      showToast(muted ? "اعلان‌های گفتگو بی‌صدا شد." : "اعلان‌های گفتگو فعال شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "تنظیم اعلان ذخیره نشد.");
    }
  }

  function openConversationOptions(mode) {
    var conversation = activeConversation();
    if (!conversation || !conversationOptionsModal || !conversationOptionsBody || !conversationOptionsTitle) return;
    state.conversationOptionsMode = mode || "";
    if (mode === "profile") {
      renderConversationProfileOptions(conversation);
    } else if (mode === "type") {
      renderConversationTypeOptions(conversation);
    } else if (mode === "reactions") {
      renderConversationReactionOptions(conversation);
    } else if (mode === "add-members") {
      renderConversationAddMembersOptions(conversation);
    } else {
      renderConversationNotificationOptions(conversation);
    }
    openModal(conversationOptionsModal, "conversation-options");
  }

  function renderConversationNotificationOptions(conversation) {
    var muted = !!(conversation.viewerState && conversation.viewerState.notificationsMuted);
    conversationOptionsTitle.textContent = "تنظیمات اعلان";
    conversationOptionsBody.innerHTML = [
      '<div class="chat-options-stack">',
      '  <button class="chat-option-row' + (!muted ? ' is-selected' : '') + '" type="button" data-notification-muted="0">',
      '    <strong>اعلان‌ها روشن باشد</strong>',
      '    <span>پیام‌های جدید این گفتگو در وضعیت عادی دیده می‌شود.</span>',
      '  </button>',
      '  <button class="chat-option-row' + (muted ? ' is-selected' : '') + '" type="button" data-notification-muted="1">',
      '    <strong>بی‌صدا کردن اعلان‌ها</strong>',
      '    <span>گفتگو در لیست می‌ماند، اما اعلان آن مزاحم نمی‌شود.</span>',
      '  </button>',
      '</div>'
    ].join("");
    Array.from(conversationOptionsBody.querySelectorAll("[data-notification-muted]")).forEach(function (button) {
      button.addEventListener("click", function () {
        var nextMuted = button.getAttribute("data-notification-muted") === "1";
        closeModal(true);
        setNotificationMuteForConversation(nextMuted);
      });
    });
  }

  function renderConversationProfileOptions(conversation) {
    conversationOptionsTitle.textContent = "ویرایش پروفایل گروه";
    conversationOptionsBody.innerHTML = [
      '<div class="chat-modal__fields chat-options-form">',
      '  <label for="conversation-option-title">نام گفتگو</label>',
      '  <input id="conversation-option-title" type="text" maxlength="80" value="' + escapeHtml(conversation.title || "") + '">',
      '  <label for="conversation-option-about">درباره گفتگو</label>',
      '  <textarea id="conversation-option-about" rows="4" maxlength="280">' + escapeHtml(conversation.about || "") + '</textarea>',
      '  <label for="conversation-option-avatar">آدرس تصویر گروه</label>',
      '  <input id="conversation-option-avatar" type="url" dir="ltr" value="' + escapeHtml(conversation.avatarUrl || "") + '" placeholder="/assets/images/logo.png">',
      '</div>',
      '<div class="chat-modal__footer chat-modal__footer--split chat-options-footer">',
      '  <button class="chat-modal-secondary-btn" type="button" data-options-cancel>انصراف</button>',
      '  <button class="chat-login-btn" type="button" data-options-save-profile>ذخیره پروفایل</button>',
      '</div>'
    ].join("");
    var titleInput = $("conversation-option-title");
    var aboutInput = $("conversation-option-about");
    var avatarInput = $("conversation-option-avatar");
    var cancel = conversationOptionsBody.querySelector("[data-options-cancel]");
    var save = conversationOptionsBody.querySelector("[data-options-save-profile]");
    if (cancel) cancel.addEventListener("click", function () { closeModal(true); });
    if (save) {
      save.addEventListener("click", async function () {
        var title = normalizeSpace(titleInput && titleInput.value);
        if (!title) {
          showToast("نام گفتگو را وارد کن.");
          if (titleInput) titleInput.focus({ preventScroll: true });
          return;
        }
        setModalBusy("conversation-options", true);
        try {
          var response = await apiPost("updateConversationProfile", {
            conversationId: conversation.id,
            title: title,
            about: normalizeSpace(aboutInput && aboutInput.value),
            avatarUrl: normalizeSpace(avatarInput && avatarInput.value)
          });
          ensureSuccessResponse(response, "پروفایل گفتگو ذخیره نشد.");
          closeModal(true);
          await refreshAfterConversationAction(response, conversation.id, { keepCurrentActive: true, forceFull: false, silent: true });
          showToast("پروفایل گفتگو ذخیره شد.");
        } catch (error) {
          showToast(error && error.message ? error.message : "پروفایل گفتگو ذخیره نشد.");
        } finally {
          setModalBusy("conversation-options", false);
        }
      });
    }
  }

  function renderConversationTypeOptions(conversation) {
    var current = conversation.settings && conversation.settings.visibility === "public" ? "public" : "private";
    conversationOptionsTitle.textContent = "نوع گروه";
    conversationOptionsBody.innerHTML = [
      '<div class="chat-options-stack">',
      '  <label class="chat-option-row' + (current === "private" ? ' is-selected' : '') + '">',
      '    <input type="radio" name="conversation-visibility" value="private"' + (current === "private" ? " checked" : "") + '>',
      '    <strong>گروه خصوصی</strong>',
      '    <span>عضویت فقط با افزودن مدیران انجام می‌شود.</span>',
      '  </label>',
      '  <label class="chat-option-row' + (current === "public" ? ' is-selected' : '') + '">',
      '    <input type="radio" name="conversation-visibility" value="public"' + (current === "public" ? " checked" : "") + '>',
      '    <strong>گروه عمومی با لینک</strong>',
      '    <span>لینک دعوت گفتگو از صفحه اطلاعات قابل کپی است.</span>',
      '  </label>',
      '</div>',
      '<div class="chat-options-link"><span>' + escapeHtml(window.location.origin + (conversation.shareUrl || (chatHomePath + (chatHomePath.indexOf("?") === -1 ? "?" : "&") + "conversationId=" + encodeURIComponent(conversation.id)))) + '</span><button type="button" data-options-copy-link>کپی</button></div>',
      '<div class="chat-modal__footer chat-modal__footer--split chat-options-footer">',
      '  <button class="chat-modal-secondary-btn" type="button" data-options-cancel>انصراف</button>',
      '  <button class="chat-login-btn" type="button" data-options-save-type>ذخیره نوع گروه</button>',
      '</div>'
    ].join("");
    var cancel = conversationOptionsBody.querySelector("[data-options-cancel]");
    var copy = conversationOptionsBody.querySelector("[data-options-copy-link]");
    var save = conversationOptionsBody.querySelector("[data-options-save-type]");
    if (cancel) cancel.addEventListener("click", function () { closeModal(true); });
    if (copy) copy.addEventListener("click", copyConversationLink);
    Array.from(conversationOptionsBody.querySelectorAll('input[name="conversation-visibility"]')).forEach(function (input) {
      input.addEventListener("change", function () {
        Array.from(conversationOptionsBody.querySelectorAll(".chat-option-row")).forEach(function (row) {
          var rowInput = row.querySelector("input");
          row.classList.toggle("is-selected", !!(rowInput && rowInput.checked));
        });
      });
    });
    if (save) {
      save.addEventListener("click", async function () {
        var checked = conversationOptionsBody.querySelector('input[name="conversation-visibility"]:checked');
        var visibility = checked ? checked.value : current;
        setModalBusy("conversation-options", true);
        try {
          var response = await apiPost("setGroupVisibility", {
            conversationId: conversation.id,
            visibility: visibility
          });
          ensureSuccessResponse(response, "نوع گروه ذخیره نشد.");
          closeModal(true);
          await refreshAfterConversationAction(response, conversation.id, { keepCurrentActive: true, forceFull: false, silent: true });
          showToast("نوع گروه ذخیره شد.");
        } catch (error) {
          showToast(error && error.message ? error.message : "نوع گروه ذخیره نشد.");
        } finally {
          setModalBusy("conversation-options", false);
        }
      });
    }
  }

  function renderConversationReactionOptions(conversation) {
    var current = conversation.settings && conversation.settings.reactionMode ? conversation.settings.reactionMode : "all";
    conversationOptionsTitle.textContent = "تنظیمات واکنش";
    var options = [
      { value: "all", title: "همه واکنش‌ها", desc: "اعضا می‌توانند از همه ایموجی‌های واکنش استفاده کنند." },
      { value: "quick", title: "انتخاب برخی از واکنش‌ها", desc: "فقط واکنش‌های پرکاربرد و امن در منوی واکنش نمایش داده می‌شود." },
      { value: "none", title: "بدون واکنش", desc: "ثبت واکنش روی پیام‌های این گفتگو غیرفعال می‌شود." }
    ];
    conversationOptionsBody.innerHTML = [
      '<div class="chat-options-stack">',
      options.map(function (item) {
        return [
          '<label class="chat-option-row' + (item.value === current ? ' is-selected' : '') + '">',
          '  <input type="radio" name="conversation-reaction-mode" value="' + escapeHtml(item.value) + '"' + (item.value === current ? " checked" : "") + '>',
          '  <strong>' + escapeHtml(item.title) + '</strong>',
          '  <span>' + escapeHtml(item.desc) + '</span>',
          '</label>'
        ].join("");
      }).join(""),
      '</div>',
      '<div class="chat-modal__footer chat-modal__footer--split chat-options-footer">',
      '  <button class="chat-modal-secondary-btn" type="button" data-options-cancel>انصراف</button>',
      '  <button class="chat-login-btn" type="button" data-options-save-reactions>ذخیره واکنش‌ها</button>',
      '</div>'
    ].join("");
    var cancel = conversationOptionsBody.querySelector("[data-options-cancel]");
    var save = conversationOptionsBody.querySelector("[data-options-save-reactions]");
    if (cancel) cancel.addEventListener("click", function () { closeModal(true); });
    Array.from(conversationOptionsBody.querySelectorAll('input[name="conversation-reaction-mode"]')).forEach(function (input) {
      input.addEventListener("change", function () {
        Array.from(conversationOptionsBody.querySelectorAll(".chat-option-row")).forEach(function (row) {
          var rowInput = row.querySelector("input");
          row.classList.toggle("is-selected", !!(rowInput && rowInput.checked));
        });
      });
    });
    if (save) {
      save.addEventListener("click", async function () {
        var checked = conversationOptionsBody.querySelector('input[name="conversation-reaction-mode"]:checked');
        var reactionMode = checked ? checked.value : current;
        setModalBusy("conversation-options", true);
        try {
          var response = await apiPost("setReactionMode", {
            conversationId: conversation.id,
            reactionMode: reactionMode
          });
          ensureSuccessResponse(response, "تنظیمات واکنش ذخیره نشد.");
          closeModal(true);
          await refreshAfterConversationAction(response, conversation.id, { keepCurrentActive: true, forceFull: false, silent: true });
          showToast("تنظیمات واکنش ذخیره شد.");
        } catch (error) {
          showToast(error && error.message ? error.message : "تنظیمات واکنش ذخیره نشد.");
        } finally {
          setModalBusy("conversation-options", false);
        }
      });
    }
  }

  function renderConversationAddMembersOptions(conversation) {
    conversationOptionsTitle.textContent = "افزودن عضو";
    conversationOptionsBody.innerHTML = '<div class="chat-picker-empty">در حال بارگذاری فهرست دانشجویان...</div>';
    loadDirectory(false).then(function () {
      var selected = new Set();
      var memberIds = new Set((conversation.members || []).map(function (member) {
        return normalizeStudentNumber(member && member.studentNumber);
      }).filter(Boolean));
      var memberSearchTimer = null;

      function renderList() {
        var queryInput = $("conversation-option-member-search");
        var query = normalizeSpace(queryInput && queryInput.value).toLowerCase();
        var users = state.directoryUsers.filter(function (user) {
          if (!user || memberIds.has(user.studentNumber)) return false;
          if (!query) return true;
          return [user.name, user.studentNumber, user.roleLabel, user.profile && user.profile.about]
            .some(function (value) { return normalizeSpace(value).toLowerCase().indexOf(query) !== -1; });
        });
        var selectedMeta = selected.size ? selected.size.toLocaleString("fa-IR") + " عضو انتخاب شد" : "عضوی انتخاب نشده است";
        conversationOptionsBody.innerHTML = [
          '<label class="conversation-search-wrap chat-options-search" for="conversation-option-member-search">',
          '  <input id="conversation-option-member-search" type="search" placeholder="جستجوی دانشجو برای افزودن..." value="' + escapeHtml(queryInput ? queryInput.value : "") + '">',
          '</label>',
          '<p class="chat-modal-meta">' + escapeHtml(selectedMeta) + '</p>',
          '<div class="chat-modal__body chat-modal__body--dense chat-options-member-list" id="conversation-option-members"></div>',
          '<div class="chat-modal__footer chat-modal__footer--split chat-options-footer">',
          '  <button class="chat-modal-secondary-btn" type="button" data-options-cancel>انصراف</button>',
          '  <button class="chat-login-btn" type="button" data-options-add-members>افزودن عضو</button>',
          '</div>'
        ].join("");
        var listNode = $("conversation-option-members");
        if (listNode) {
          if (!users.length) {
            listNode.innerHTML = '<div class="chat-picker-empty">دانشجوی جدیدی برای افزودن پیدا نشد.</div>';
          } else {
            users.forEach(function (user) {
              var checked = selected.has(user.studentNumber);
              var item = document.createElement("button");
              item.type = "button";
              item.className = "chat-picker-item" + (checked ? " is-selected" : "");
              item.innerHTML = [
                '<span class="chat-picker-item__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(user.name)) + '</span></span>',
                '<span class="chat-picker-item__copy">',
                '  <strong>' + escapeHtml(user.name) + '</strong>',
                '  <span>' + escapeHtml((user.profile && user.profile.about) || userRoleMetaText(user)) + '</span>',
                '</span>',
                '<input class="chat-picker-check" type="checkbox"' + (checked ? ' checked' : '') + ' tabindex="-1" aria-hidden="true">'
              ].join("");
              var avatar = item.querySelector(".chat-picker-item__avatar");
              renderAvatar(avatar, item.querySelector("img"), item.querySelector(".chat-picker-item__avatar span"), user.profile && user.profile.avatarUrl, user.name);
              item.addEventListener("click", function () {
                if (selected.has(user.studentNumber)) {
                  selected.delete(user.studentNumber);
                } else {
                  selected.add(user.studentNumber);
                }
                renderList();
              });
              listNode.appendChild(item);
            });
          }
        }
        var nextQuery = $("conversation-option-member-search");
        if (nextQuery) {
          nextQuery.addEventListener("input", function () {
            window.clearTimeout(memberSearchTimer);
            memberSearchTimer = window.setTimeout(renderList, 200);
          });
          if (query) {
            nextQuery.focus({ preventScroll: true });
            nextQuery.setSelectionRange(nextQuery.value.length, nextQuery.value.length);
          }
        }
        var cancel = conversationOptionsBody.querySelector("[data-options-cancel]");
        var add = conversationOptionsBody.querySelector("[data-options-add-members]");
        if (cancel) cancel.addEventListener("click", function () { closeModal(true); });
        if (add) {
          add.disabled = selected.size <= 0;
          add.addEventListener("click", async function () {
            if (!selected.size) {
              showToast("حداقل یک عضو جدید انتخاب کن.");
              return;
            }
            setModalBusy("conversation-options", true);
            try {
              var response = await apiPost("addMembers", {
                conversationId: conversation.id,
                membersJson: JSON.stringify(Array.from(selected))
              });
              ensureSuccessResponse(response, "افزودن عضو انجام نشد.");
              closeModal(true);
              await refreshAfterConversationAction(response, conversation.id, { keepCurrentActive: true, forceFull: false, silent: true });
              showToast("عضوهای جدید اضافه شدند.");
            } catch (error) {
              showToast(error && error.message ? error.message : "افزودن عضو انجام نشد.");
            } finally {
              setModalBusy("conversation-options", false);
            }
          });
        }
      }

      renderList();
    }).catch(function (error) {
      conversationOptionsBody.innerHTML = '<div class="chat-picker-empty">بارگذاری فهرست دانشجویان انجام نشد.</div>';
      showToast(error && error.message ? error.message : "بارگذاری فهرست دانشجویان انجام نشد.");
    });
  }

  function updateInfoSheet() {
    if (!infoSheet) return;
    var conversation = activeConversation();
    if (!conversation) {
      if (infoTitle) infoTitle.textContent = "گفت‌وگو";
      if (infoStatus) infoStatus.textContent = "گفت‌وگو انتخاب نشده است";
      if (infoAbout) {
        infoAbout.textContent = "";
        infoAbout.hidden = true;
      }
      if (infoAvatar) setPresenceBadge(infoAvatar, null);
      if (infoProfile) {
        infoProfile.classList.remove("is-clickable");
        delete infoProfile.dataset.profileHref;
      }
      if (infoIdentityRows) infoIdentityRows.innerHTML = "";
      if (infoContentTabs) infoContentTabs.innerHTML = "";
      if (infoContentTable) infoContentTable.innerHTML = "";
      if (infoMembers) infoMembers.innerHTML = "";
      if (infoMembersBlock) infoMembersBlock.hidden = true;
      if (infoActionsBlock) infoActionsBlock.hidden = true;
      if (adminTools) adminTools.hidden = true;
      if (infoNotificationBtn) infoNotificationBtn.hidden = true;
      if (infoEditProfileBtn) infoEditProfileBtn.hidden = true;
      if (infoGroupTypeBtn) infoGroupTypeBtn.hidden = true;
      if (infoReactionSettingsBtn) infoReactionSettingsBtn.hidden = true;
      if (infoAddMembersBtn) infoAddMembersBtn.hidden = true;
      if (infoProfileLink) infoProfileLink.href = "/account/?from=chat#account-profile";
      if (infoSecurityLink) infoSecurityLink.href = "/account/?from=chat#account-security";
      if (infoAccountLink) infoAccountLink.href = "/account/?from=chat";
      return;
    }

    if (infoTitle) infoTitle.textContent = conversation.title;
    var livePresenceText = conversationPresenceText(conversation);
    if (infoStatus) {
      var statusParts = [];
      if (conversation.type === "direct") {
        statusParts.push(livePresenceText || "گفتگوی خصوصی");
      } else if (conversation.type === "saved") {
        statusParts.push("فقط شما");
        statusParts.push("یادداشت شخصی");
      } else if (conversation.type === "class-group") {
        statusParts.push(conversation.memberCount.toLocaleString("fa-IR") + " عضو");
        statusParts.push("گروه اجباری");
      } else {
        statusParts.push(conversation.memberCount.toLocaleString("fa-IR") + " عضو");
        statusParts.push("گروه");
      }
      if (livePresenceText && conversation.type !== "direct") {
        statusParts.push(livePresenceText);
      }
      var unread = Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0)));
      if (unread > 0) {
        statusParts.push(unread.toLocaleString("fa-IR") + " خوانده‌نشده");
      }
      if (conversation.viewerState && conversation.viewerState.archived) {
        statusParts.push("بایگانی");
      }
      infoStatus.textContent = statusParts.join("  •  ");
    }
    var aboutText = normalizeSpace(conversation.about);
    if (!aboutText && conversation.type === "direct" && conversation.peer && conversation.peer.profile) {
      aboutText = normalizeSpace(conversation.peer.profile.about);
    } else if (!aboutText && conversation.type === "saved") {
      aboutText = "یادداشت‌ها، فورواردها و پیام‌های شخصی خودت را اینجا نگه دار.";
    }
    if (infoAbout) {
      if (aboutText) {
        infoAbout.textContent = aboutText;
        infoAbout.hidden = false;
      } else {
        infoAbout.textContent = "";
        infoAbout.hidden = true;
      }
    }
    if (infoAvatar && infoAvatarImage && infoAvatarFallback) {
      renderAvatar(infoAvatar, infoAvatarImage, infoAvatarFallback, conversation.avatarUrl, conversation.title);
      setPresenceBadge(infoAvatar, conversation);
    }
    if (infoProfile) {
      if (conversation.type === "direct" && conversation.peer && conversation.peer.studentNumber) {
        infoProfile.classList.add("is-clickable");
        infoProfile.dataset.profileHref = "/account/?studentNumber=" + encodeURIComponent(conversation.peer.studentNumber) + "&from=chat#account-info";
      } else {
        infoProfile.classList.remove("is-clickable");
        delete infoProfile.dataset.profileHref;
      }
    }
    if (infoProfileLink) infoProfileLink.href = "/account/?from=chat#account-profile";
    if (infoSecurityLink) infoSecurityLink.href = "/account/?from=chat#account-security";
    if (infoAccountLink) infoAccountLink.href = "/account/?from=chat";
    if (infoNotificationBtn) {
      var canManageNotifications = !!(conversation.permissions && conversation.permissions.canMuteConversation);
      infoNotificationBtn.hidden = !canManageNotifications;
      if (canManageNotifications) {
        var notificationsMuted = !!(conversation.viewerState && conversation.viewerState.notificationsMuted);
        var notificationLabel = infoNotificationBtn.querySelector(".chat-info-quick-action__label");
        if (notificationLabel) notificationLabel.textContent = notificationsMuted ? "روشن کردن اعلان" : "بی‌صدا کردن";
        infoNotificationBtn.classList.toggle("is-active", notificationsMuted);
      }
    }
    if (infoEditProfileBtn) {
      infoEditProfileBtn.hidden = !(conversation.permissions && conversation.permissions.canEditProfile);
    }
    if (infoGroupTypeBtn) {
      infoGroupTypeBtn.hidden = !(conversation.permissions && conversation.permissions.canEditGroupType);
    }
    if (infoReactionSettingsBtn) {
      infoReactionSettingsBtn.hidden = !(conversation.permissions && conversation.permissions.canEditReactions);
    }
    if (infoAddMembersBtn) {
      infoAddMembersBtn.hidden = !(conversation.permissions && conversation.permissions.canAddMembers);
    }

    var canViewStudentNumbers = canCurrentUserViewStudentNumbers();
    var roleLabel = conversation.permissions && conversation.permissions.canManageConversation
      ? "مدیر گفتگو"
      : "عضو گفتگو";
    if (conversation.type === "saved") {
      roleLabel = "فقط شما";
    }
    var infoRows = [
      {
        label: "نوع گفتگو",
        value: conversationKindLabel(conversation)
      }
    ];
    if (conversation.type !== "saved") {
      infoRows.push({
        label: "نقش شما",
        value: roleLabel
      });
    }
    if (conversation.type === "direct" && conversation.peer && canViewStudentNumbers) {
      infoRows.push({
        label: "شناسه مخاطب",
        value: "@" + normalizeSpace(conversation.peer.studentNumber || "")
      });
    } else if (canViewStudentNumbers && conversation.id && conversation.type !== "direct" && conversation.type !== "saved") {
      infoRows.push({
        label: "شناسه گفتگو",
        value: conversation.id
      });
    }
    if (conversation.viewerState && conversation.viewerState.archived) {
      infoRows.push({
        label: "وضعیت گفتگو",
        value: "بایگانی‌شده",
        tone: "muted"
      });
    }
    if (!(conversation.permissions && conversation.permissions.canSend)) {
      infoRows.push({
        label: "ارسال پیام",
        value: "غیرفعال",
        tone: "danger"
      });
    }
    var unreadCount = Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0)));
    if (unreadCount > 0) {
      infoRows.push({
        label: "خوانده‌نشده",
        value: unreadCount.toLocaleString("fa-IR")
      });
    }
    if (livePresenceText && conversation.type !== "direct") {
      infoRows.push({
        label: "وضعیت حضور",
        value: livePresenceText
      });
    }
    if (!isPrivateLikeConversationType(conversation.type)) {
      infoRows.push({
        label: "واکنش‌ها",
        value: reactionModeLabel(conversation.settings && conversation.settings.reactionMode)
      });
    }
    if (conversation.type === "group") {
      infoRows.push({
        label: "نوع عضویت",
        value: groupVisibilityLabel(conversation.settings && conversation.settings.visibility)
      });
    }
    renderInfoRows(infoIdentityRows, infoRows);

    renderInfoContentOverview();

    if (infoMembersBlock) {
      var isMembersConversation = conversation.type === "group" || conversation.type === "channel" || conversation.type === "class-group";
      infoMembersBlock.hidden = !isMembersConversation;
      if (isMembersConversation && infoMembersLabel) {
        var memberTotal = Array.isArray(conversation.members) && conversation.members.length
          ? conversation.members.length
          : Math.max(0, Math.floor(toNumber(conversation.memberCount, 0)));
        infoMembersLabel.textContent = memberTotal > 0 ? "اعضا  ·  " + memberTotal.toLocaleString("fa-IR") : "اعضا";
      }
    }
    if (infoMembers) {
      infoMembers.innerHTML = "";
      var members = Array.isArray(conversation.members) ? conversation.members : [];
      if (!members.length) {
        var empty = document.createElement("div");
        empty.className = "chat-member chat-member--empty";
        empty.innerHTML = "<strong>در حال بارگذاری اعضا</strong><span>برای این گفت‌وگو اطلاعات اعضا در حال دریافت است.</span>";
        infoMembers.appendChild(empty);
      } else {
        members.forEach(function (member) {
          var node = document.createElement("div");
          node.className = "chat-member";
          var tag = normalizeSpace(member.conversationTag);
          var canManageMember = !!(conversation.permissions && conversation.permissions.canManageConversation && conversation.type === "group");
          if (member.studentNumber) {
            node.classList.add("chat-member--clickable");
            node.setAttribute("data-member-student", member.studentNumber);
          }
          var presenceText = userPresenceText(member, conversation.id);
          var isLivePresence = presenceText === "آنلاین" || presenceText === "در حال نوشتن…";
          var subtitleText = userRoleMetaText(member) + (presenceText ? " • " + presenceText : "");
          node.innerHTML = [
            '<span class="chat-member__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(member.name)) + "</span></span>",
            '<span class="chat-member__copy">',
            "  <strong>" + escapeHtml(member.name) + "</strong>",
            '  <span class="' + (isLivePresence ? "is-live-presence" : "") + '">' + escapeHtml(subtitleText) + (tag ? ' <b class="chat-member-tag">' + escapeHtml(tag) + '</b>' : "") + "</span>",
            "</span>",
            canManageMember ? '<button class="chat-member__menu-btn" type="button" data-member-menu="' + escapeHtml(member.studentNumber) + '" aria-label="عملیات عضو"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="6" r="1.6" fill="currentColor" /><circle cx="12" cy="12" r="1.6" fill="currentColor" /><circle cx="12" cy="18" r="1.6" fill="currentColor" /></svg></button>' : ""
          ].join("");
          var avatar = node.querySelector(".chat-member__avatar");
          var image = node.querySelector("img");
          var fallback = node.querySelector(".chat-member__avatar span");
          renderAvatar(avatar, image, fallback, member.profile && member.profile.avatarUrl, member.name);
          var memberPresence = member && member.presence ? normalizePresenceState(member.presence, conversation.id) : null;
          if (memberPresence && memberPresence.isTyping) {
            avatar.dataset.presence = "typing";
          } else if (memberPresence && memberPresence.isOnline) {
            avatar.dataset.presence = "online";
          }
          infoMembers.appendChild(node);
        });
      }
    }

    if (adminTools) {
      adminTools.hidden = !(conversation.permissions && conversation.permissions.canManageConversation);
    }
    if (infoActionsBlock) {
      var canArchive = !!(conversation.permissions && conversation.permissions.canArchiveConversation);
      var isArchived = !!(conversation.viewerState && conversation.viewerState.archived);
      if (archiveBtn) archiveBtn.hidden = !canArchive || isArchived;
      if (unarchiveBtn) unarchiveBtn.hidden = !canArchive || !isArchived;
      if (clearHistoryBtn) clearHistoryBtn.hidden = !(conversation.permissions && conversation.permissions.canClearHistory);
      if (leaveBtn) leaveBtn.hidden = !(conversation.permissions && conversation.permissions.canLeaveConversation);
      if (deleteConversationBtn) deleteConversationBtn.hidden = !(conversation.permissions && conversation.permissions.canDeleteConversation);

      var hasPrimaryAction = false;
      [archiveBtn, unarchiveBtn, clearHistoryBtn, leaveBtn, deleteConversationBtn].forEach(function (button) {
        if (button && !button.hidden) hasPrimaryAction = true;
      });
      infoActionsBlock.hidden = !hasPrimaryAction;
    }
    updateMuteUi(conversation.settings);
    updatePinnedUi();
  }

  function closeInfoSheet() {
    if (!infoSheet || !infoSheetBackdrop) return;
    infoSheet.classList.remove("is-open");
    if (chatApp) {
      chatApp.classList.remove("has-info-open");
    }
    infoSheet.hidden = true;
    infoSheetBackdrop.classList.remove("is-open");
    infoSheetBackdrop.hidden = true;
    state.infoSheetOpen = false;
    refreshTransportBinding();
    updateMobileNav();
  }

  async function openInfoSheet() {
    var conversation = activeConversation();
    if (!conversation || !infoSheet || !infoSheetBackdrop) return;
    state.infoSheetOpen = true;
    if (chatApp) {
      chatApp.classList.add("has-info-open");
    }
    infoSheet.hidden = false;
    var mobileSheet = isMobileViewport();
    infoSheetBackdrop.hidden = !mobileSheet;
    window.requestAnimationFrame(function () {
      infoSheet.classList.add("is-open");
      if (mobileSheet) {
        infoSheetBackdrop.classList.add("is-open");
      } else {
        infoSheetBackdrop.classList.remove("is-open");
      }
    });
    if (!Array.isArray(conversation.members) || !conversation.members.length) {
      await syncConversation({ forceFull: false, includeMembers: true, silent: true });
    } else {
      updateInfoSheet();
    }
    refreshTransportBinding();
    updateMobileNav();
  }

  async function setMemberTag(studentNumber) {
    var conversation = activeConversation();
    var memberId = normalizeStudentNumber(studentNumber);
    if (!conversation || !memberId) return;
    var member = (conversation.members || []).find(function (item) {
      return normalizeStudentNumber(item.studentNumber) === memberId;
    });
    var currentTag = normalizeSpace(member && member.conversationTag);
    var nextTag = window.prompt("تگ کنار نام کاربر", currentTag);
    if (nextTag === null) return;
    nextTag = normalizeSpace(nextTag).slice(0, 32);
    try {
      var response = await apiPost("setMemberTag", {
        conversationId: conversation.id,
        memberStudentNumber: memberId,
        tag: nextTag
      });
      ensureSuccessResponse(response, "تگ عضو ذخیره نشد.");
      var nextConversation = normalizeConversation(response.conversation);
      if (nextConversation) {
        upsertConversation(nextConversation);
        rebuildConversationsFromMap();
      }
      updateInfoSheet();
      renderConversationList();
      showToast("تگ عضو ذخیره شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "تگ عضو ذخیره نشد.");
    }
  }

  async function setMemberAdmin(studentNumber, admin) {
    var conversation = activeConversation();
    var memberId = normalizeStudentNumber(studentNumber);
    if (!conversation || !memberId) return;
    try {
      var response = await apiPost("setMemberAdmin", {
        conversationId: conversation.id,
        memberStudentNumber: memberId,
        admin: admin ? "1" : "0"
      });
      ensureSuccessResponse(response, "وضعیت مدیر ذخیره نشد.");
      var nextConversation = normalizeConversation(response.conversation);
      if (nextConversation) {
        upsertConversation(nextConversation);
        rebuildConversationsFromMap();
      }
      updateInfoSheet();
      showToast(admin ? "مدیر اضافه شد." : "دسترسی مدیر برداشته شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "وضعیت مدیر ذخیره نشد.");
    }
  }

  function modalNodeByKey(key) {
    if (key === "dm") return dmModal;
    if (key === "group") return groupModal;
    if (key === "forward") return forwardModal;
    if (key === "reaction") return reactionModal;
    if (key === "reaction-details") return reactionDetailsModal;
    if (key === "edit") return editModal;
    if (key === "poll") return pollModal;
    if (key === "conversation-options") return conversationOptionsModal;
    if (key === "confirm") return confirmModal;
    if (key === "receipts") return receiptsModal;
    return null;
  }

  function resolveConfirmDialog(result) {
    if (!state.confirmDialog || typeof state.confirmDialog.resolve !== "function") return;
    var resolver = state.confirmDialog.resolve;
    state.confirmDialog = null;
    resolver(!!result);
  }

  async function openConfirmDialog(options) {
    var opts = asObject(options) || {};
    var title = normalizeSpace(opts.title) || "تایید عملیات";
    var message = normalizeSpace(opts.message) || "آیا از انجام این عملیات مطمئن هستی؟";
    var acceptLabel = normalizeSpace(opts.acceptLabel) || "تایید";
    var danger = !!opts.danger;

    if (!confirmModal || !confirmAcceptBtn || !confirmCancelBtn || !confirmModalText || !confirmModalTitle) {
      return window.confirm(message);
    }

    confirmModalTitle.textContent = title;
    confirmModalText.textContent = message;
    confirmAcceptBtn.textContent = acceptLabel;
    confirmAcceptBtn.classList.toggle("chat-login-btn-danger", danger);

    openModal(confirmModal, "confirm");
    return new Promise(function (resolve) {
      state.confirmDialog = { resolve: resolve };
    });
  }

  function closeModal(force) {
    var hardClose = force === true;
    var closingKey = state.modalOpen;
    if (!hardClose && state.modalOpen === "dm" && state.pendingDirectStart) {
      showToast("در حال ایجاد گفت‌وگوی خصوصی است...");
      return;
    }
    if (!hardClose && state.modalOpen === "group" && state.pendingGroupCreate) {
      showToast("در حال ساخت گروه یا کانال است...");
      return;
    }
    var hadOpenModal = !!state.modalOpen;
    if (modalBackdrop) {
      modalBackdrop.classList.remove("is-open");
      modalBackdrop.hidden = true;
    }
    [dmModal, groupModal, forwardModal, reactionModal, reactionDetailsModal, editModal, pollModal, conversationOptionsModal, confirmModal, receiptsModal].forEach(function (node) {
      if (!node) return;
      node.classList.remove("is-open");
      node.classList.remove("is-busy");
      node.setAttribute("aria-busy", "false");
      node.hidden = true;
    });
    state.modalOpen = "";
    if (document.body) {
      document.body.classList.remove("chat-modal-open");
    }
    if (hadOpenModal && closingKey === "group" && groupModal) {
      resetGroupCreateFlow();
    }
    if (hadOpenModal && closingKey === "forward") {
      state.pendingForwardMessageId = null;
      state.pendingForwardMessageIds = [];
      state.forwardIncludeSenderName = true;
      if (forwardIncludeSenderInput) {
        forwardIncludeSenderInput.checked = true;
      }
      if (forwardSearch) forwardSearch.value = "";
      if (forwardList) forwardList.innerHTML = "";
      if (forwardSourcePreview) forwardSourcePreview.innerHTML = "";
    }
    if (hadOpenModal && closingKey === "reaction") {
      state.pendingReactionMessageId = null;
      state.reactionCategory = "recent";
      if (reactionSearch) reactionSearch.value = "";
      if (reactionTabs) reactionTabs.innerHTML = "";
      if (reactionGrid) reactionGrid.innerHTML = "";
    }
    if (hadOpenModal && closingKey === "reaction-details") {
      state.reactionDetailsRequestToken += 1;
      if (reactionDetailsModalTitle) {
        reactionDetailsModalTitle.textContent = "فهرست واکنش‌ها";
      }
      if (reactionDetailsList) {
        reactionDetailsList.innerHTML = "";
      }
    }
    if (hadOpenModal && closingKey === "edit") {
      state.pendingEditMessageId = null;
      if (editTextInput) editTextInput.value = "";
    }
    if (hadOpenModal && closingKey === "poll") {
      resetPollModal();
    }
    if (hadOpenModal && closingKey === "conversation-options") {
      state.conversationOptionsMode = "";
      if (conversationOptionsBody) conversationOptionsBody.innerHTML = "";
    }
    if (hadOpenModal && closingKey === "confirm") {
      resolveConfirmDialog(false);
    }
    if (hadOpenModal && closingKey === "receipts" && receiptsList) {
      receiptsList.innerHTML = "";
    }
    if (hadOpenModal && isMobileViewport()) {
      setMobileView(state.activeConversationId ? "thread" : "list");
    }
    updateFabVisibility();
    updateMobileNav();
  }

  function setModalBusy(key, busy) {
    var target = modalNodeByKey(key);
    if (!target) return;
    target.classList.toggle("is-busy", !!busy);
    target.setAttribute("aria-busy", busy ? "true" : "false");
  }

  function openModal(node, key) {
    if (!node || !modalBackdrop) return;
    closeContextMenu();
    closeInfoSheet();
    setUploadSheetOpen(false); setEmojiPanelOpen(false);
    closeModal(true);
    node.hidden = false;
    modalBackdrop.hidden = false;
    window.requestAnimationFrame(function () {
      node.classList.add("is-open");
      modalBackdrop.classList.add("is-open");
    });
    state.modalOpen = key || "";
    node.classList.remove("is-busy");
    node.setAttribute("aria-busy", "false");
    if (document.body) {
      document.body.classList.add("chat-modal-open");
    }
    if (key === "group") {
      setGroupCreateStep("members");
    }
    if (isMobileViewport()) {
      if (key === "group") {
        setMobileView("group");
      } else if (key === "dm" || key === "forward") {
        setMobileView("dm");
      } else {
        setMobileView(state.activeConversationId ? "thread" : "list");
      }
    }
    updateFabVisibility();
    updateMobileNav();
  }

  function syncCurrentUserAvatar() {
    if (!accountBtn || !accountBtnAvatarImage || !accountBtnAvatarFallback) return;
    var profile = asObject(state.me.profile) || {};
    renderAvatar(accountBtn, accountBtnAvatarImage, accountBtnAvatarFallback, profile.avatarUrl, state.me.name || state.me.studentNumber);
  }

  function usersForDirectory() {
    return state.directoryUsers.filter(function (user) {
      return !!user && user.studentNumber !== state.me.studentNumber;
    });
  }

  async function loadDirectory(force) {
    if (!force && state.directoryLoaded) {
      return usersForDirectory();
    }

    var response = await apiGet("directory", {});
    if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
      throw new Error((response && response.error) || "نشست شما منقضی شده است.");
    }
    ensureSuccessResponse(response, "بارگذاری فهرست کاربران انجام نشد.");

    var users = (Array.isArray(response.users) ? response.users : [])
      .map(normalizeUser)
      .filter(Boolean)
      .sort(function (left, right) {
        return toText(left.name).localeCompare(toText(right.name), "fa");
      });

    state.directoryUsers = users;
    state.directoryLoaded = true;
    return usersForDirectory();
  }

  function mentionCandidates() {
    var conversation = activeConversation();
    var pool = [];
    if (conversation) {
      if (Array.isArray(conversation.members) && conversation.members.length) {
        pool = conversation.members.slice();
      } else if (conversation.peer) {
        pool = [conversation.peer];
      }
    }
    if (!pool.length) {
      pool = usersForDirectory();
    }
    var seen = new Set();
    return pool.filter(function (user) {
      var studentNumber = normalizeStudentNumber(user && user.studentNumber);
      if (!studentNumber || studentNumber === normalizeStudentNumber(state.me && state.me.studentNumber) || seen.has(studentNumber)) {
        return false;
      }
      seen.add(studentNumber);
      return true;
    }).sort(function (left, right) {
      var leftScore = (left && left.isOwner ? 30 : 0) + (left && left.isRepresentative ? 20 : 0) + (left && left.canModerateChat ? 10 : 0);
      var rightScore = (right && right.isOwner ? 30 : 0) + (right && right.isRepresentative ? 20 : 0) + (right && right.canModerateChat ? 10 : 0);
      if (leftScore !== rightScore) {
        return rightScore - leftScore;
      }
      return toText(left && left.name).localeCompare(toText(right && right.name), "fa");
    });
  }

  function currentMentionMatch() {
    if (!chatTextEl || chatTextEl.selectionStart == null || chatTextEl.selectionStart !== chatTextEl.selectionEnd) {
      return null;
    }
    var caret = Math.max(0, Math.floor(toNumber(chatTextEl.selectionStart, 0)));
    var prefix = toText(chatTextEl.value).slice(0, caret);
    var match = prefix.match(/(^|[\s(\[<{])@([^\s@]{0,32})$/);
    if (!match) return null;
    return {
      query: toText(match[2] || ""),
      start: caret - toText(match[2] || "").length - 1,
      end: caret
    };
  }

  function closeMentionSuggestions() {
    state.mentionQuery = "";
    state.mentionSuggestions = [];
    state.mentionSelectedIndex = -1;
    state.mentionTokenStart = -1;
    state.mentionTokenEnd = -1;
    if (mentionSuggestions) {
      mentionSuggestions.hidden = true;
      mentionSuggestions.innerHTML = "";
    }
  }

  function renderMentionSuggestions() {
    if (!mentionSuggestions) return;
    var suggestions = Array.isArray(state.mentionSuggestions) ? state.mentionSuggestions : [];
    if (!suggestions.length) {
      mentionSuggestions.hidden = true;
      mentionSuggestions.innerHTML = "";
      return;
    }
    mentionSuggestions.hidden = false;
    mentionSuggestions.innerHTML = suggestions.map(function (user, index) {
      var selected = index === state.mentionSelectedIndex;
      var roleBadge = user && user.isOwner
        ? "مالک"
        : (user && user.isRepresentative ? "نماینده" : (user && user.canModerateChat ? "مدیر" : ""));
      var subtitle = [normalizeSpace(user.roleLabel), normalizeSpace(user.profile && user.profile.about)].filter(Boolean).slice(0, 2).join(" • ");
      return [
        '<button type="button" class="composer-mention-item' + (selected ? " is-selected" : "") + '" data-mention-index="' + index + '">',
        '  <span class="composer-mention-item__avatar">' + escapeHtml(avatarLabel(user.name || user.studentNumber)) + "</span>",
        '  <span class="composer-mention-item__copy">',
        '    <strong data-digit-locale="latin">' + escapeHtml(user.name || user.studentNumber) + (roleBadge ? ('<span class="composer-mention-item__badge">' + escapeHtml(roleBadge) + '</span>') : "") + "</strong>",
        subtitle ? ('    <small data-digit-locale="latin">' + escapeHtml(subtitle) + "</small>") : "",
        "  </span>",
        "</button>"
      ].join("");
    }).join("");
    Array.from(mentionSuggestions.querySelectorAll("[data-mention-index]")).forEach(function (button) {
      button.addEventListener("mousedown", function (event) {
        event.preventDefault();
      });
      button.addEventListener("click", function (event) {
        event.preventDefault();
        applyMentionSuggestionAtIndex(Math.floor(toNumber(button.getAttribute("data-mention-index"), -1)));
      });
    });
  }

  function refreshMentionSuggestions() {
    var conversation = activeConversation();
    var match = currentMentionMatch();
    if (!conversation || !match || !chatTextEl || chatTextEl.disabled || document.activeElement !== chatTextEl) {
      closeMentionSuggestions();
      return;
    }

    state.mentionTokenStart = Math.max(0, match.start);
    state.mentionTokenEnd = Math.max(state.mentionTokenStart, match.end);
    state.mentionQuery = toText(match.query || "");

    var query = state.mentionQuery.toLowerCase();
    var suggestions = mentionCandidates().filter(function (user) {
      if (!query) return true;
      var haystack = [
        user.name,
        user.roleLabel,
        user.profile && user.profile.about,
        user.studentNumber
      ].join(" ").toLowerCase();
      return haystack.indexOf(query) !== -1;
    }).slice(0, 6);

    if (!suggestions.length && !state.directoryLoaded) {
      loadDirectory(false).then(function () {
        refreshMentionSuggestions();
      }).catch(function () {});
    }

    state.mentionSuggestions = suggestions;
    if (!suggestions.length) {
      state.mentionSelectedIndex = -1;
      renderMentionSuggestions();
      return;
    }
    if (state.mentionSelectedIndex < 0 || state.mentionSelectedIndex >= suggestions.length) {
      state.mentionSelectedIndex = 0;
    }
    renderMentionSuggestions();
  }

  function applyMentionSuggestionAtIndex(index) {
    if (!chatTextEl) return false;
    var suggestions = Array.isArray(state.mentionSuggestions) ? state.mentionSuggestions : [];
    var user = suggestions[index] || null;
    if (!user) return false;
    var studentNumber = normalizeStudentNumber(user.studentNumber);
    if (!studentNumber) return false;
    var value = toText(chatTextEl.value);
    var start = Math.max(0, state.mentionTokenStart);
    var end = Math.max(start, state.mentionTokenEnd);
    chatTextEl.value = value.slice(0, start) + "@" + studentNumber + " " + value.slice(end);
    var nextCaret = start + studentNumber.length + 2;
    chatTextEl.setSelectionRange(nextCaret, nextCaret);
    autosizeComposer();
    closeMentionSuggestions();
    handleComposerTypingActivity();
    chatTextEl.focus({ preventScroll: true });
    return true;
  }

  function moveMentionSelection(delta) {
    var suggestions = Array.isArray(state.mentionSuggestions) ? state.mentionSuggestions : [];
    if (!suggestions.length) return;
    var next = state.mentionSelectedIndex + delta;
    if (next < 0) next = suggestions.length - 1;
    if (next >= suggestions.length) next = 0;
    state.mentionSelectedIndex = next;
    renderMentionSuggestions();
  }

  function renderDmList() {
    if (!dmList) return;

    var query = normalizeSpace(dmSearch && dmSearch.value).toLowerCase();
    var savedMatchesQuery = !query || "پیام های ذخیره شده ذخیره یادداشت شخصی".indexOf(query) !== -1;
    var users = usersForDirectory().filter(function (user) {
      if (!query) return true;
      var haystack = [
        user.name,
        user.roleLabel,
        user.profile && user.profile.about,
        canCurrentUserViewStudentNumbers() ? user.studentNumber : ""
      ].join(" ").toLowerCase();
      return haystack.indexOf(query) !== -1;
    });

    if (!users.length && !savedMatchesQuery) {
      dmList.innerHTML = '<div class="chat-picker-empty">کاربری برای شروع گفتگو پیدا نشد.</div>';
      return;
    }

    dmList.innerHTML = "";
    if (savedMatchesQuery) {
      var savedItem = document.createElement("button");
      savedItem.type = "button";
      savedItem.className = "chat-picker-item chat-picker-item--saved";
      savedItem.innerHTML = [
        '<span class="chat-picker-item__avatar" data-has-avatar="0"><img alt="" hidden><span>ذ</span></span>',
        '<span class="chat-picker-item__copy">',
        '  <strong>پیام‌های ذخیره‌شده</strong>',
        '  <span>فقط برای خودت</span>',
        '</span>',
        '<span class="chat-picker-check">‹</span>'
      ].join("");
      savedItem.addEventListener("click", function () {
        openSavedMessagesConversation();
      });
      dmList.appendChild(savedItem);
    }
    users.forEach(function (user) {
      var item = document.createElement("button");
      item.type = "button";
      item.className = "chat-picker-item";
      item.innerHTML = [
        '<span class="chat-picker-item__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(user.name)) + '</span></span>',
        '<span class="chat-picker-item__copy">',
        '  <strong>' + escapeHtml(user.name) + '</strong>',
        '  <span>' + escapeHtml((user.profile && user.profile.about) || userRoleMetaText(user)) + '</span>',
        '</span>',
        '<span class="chat-picker-check">‹</span>'
      ].join("");

      var avatar = item.querySelector(".chat-picker-item__avatar");
      var image = item.querySelector("img");
      var fallback = item.querySelector(".chat-picker-item__avatar span");
      renderAvatar(avatar, image, fallback, user.profile && user.profile.avatarUrl, user.name);

      item.addEventListener("click", function () {
        startDirectConversation(user.studentNumber);
      });

      dmList.appendChild(item);
    });
  }

  function renderGroupMembersPicker() {
    updateGroupSelectionMeta();
    renderGroupSelectedMembers();
    if (!groupMembers) return;

    var query = normalizeSpace(groupSearch && groupSearch.value).toLowerCase();
    var users = usersForDirectory().filter(function (user) {
      if (!query) return true;
      var haystack = [
        user.name,
        user.roleLabel,
        user.profile && user.profile.about,
        canCurrentUserViewStudentNumbers() ? user.studentNumber : ""
      ].join(" ").toLowerCase();
      return haystack.indexOf(query) !== -1;
    }).sort(function (left, right) {
      var leftSelected = state.groupMemberSelection.has(left.studentNumber);
      var rightSelected = state.groupMemberSelection.has(right.studentNumber);
      if (leftSelected !== rightSelected) {
        return leftSelected ? -1 : 1;
      }
      return toText(left.name).localeCompare(toText(right.name), "fa");
    });

    if (!users.length) {
      groupMembers.innerHTML = '<div class="chat-picker-empty">دانشجویی برای افزودن پیدا نشد.</div>';
      return;
    }

    groupMembers.innerHTML = "";
    users.forEach(function (user) {
      var selected = state.groupMemberSelection.has(user.studentNumber);
      var item = document.createElement("button");
      item.type = "button";
      item.className = "chat-picker-item" + (selected ? " is-selected" : "");
      item.innerHTML = [
        '<span class="chat-picker-item__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(user.name)) + '</span></span>',
        '<span class="chat-picker-item__copy">',
        '  <strong>' + escapeHtml(user.name) + '</strong>',
        '  <span>' + escapeHtml((user.profile && user.profile.about) || userRoleMetaText(user)) + '</span>',
        '</span>',
        '<input class="chat-picker-check" type="checkbox"' + (selected ? ' checked' : '') + ' tabindex="-1" aria-hidden="true">'
      ].join("");

      var avatar = item.querySelector(".chat-picker-item__avatar");
      var image = item.querySelector("img");
      var fallback = item.querySelector(".chat-picker-item__avatar span");
      renderAvatar(avatar, image, fallback, user.profile && user.profile.avatarUrl, user.name);

      item.addEventListener("click", function () {
        if (state.pendingGroupCreate) return;
        if (state.groupMemberSelection.has(user.studentNumber)) {
          state.groupMemberSelection.delete(user.studentNumber);
        } else {
          state.groupMemberSelection.add(user.studentNumber);
        }
        renderGroupMembersPicker();
      });

      groupMembers.appendChild(item);
    });
  }

  function reactionPickerSourceMessage() {
    return findMessage(state.pendingReactionMessageId);
  }

  function renderReactionPicker() {
    if (!reactionGrid) return;
    var sourceMessage = reactionPickerSourceMessage();
    if (!sourceMessage) {
      reactionGrid.innerHTML = '<div class="chat-picker-empty">پیام مرجع برای واکنش پیدا نشد.</div>';
      if (reactionTabs) reactionTabs.innerHTML = "";
      return;
    }

    var query = normalizeSpace(reactionSearch && reactionSearch.value);
    var normalizedQuery = query.toLowerCase();
    var ownReactions = new Set(
      reactionEntries(sourceMessage)
        .filter(function (entry) { return entry.own; })
        .map(function (entry) { return entry.emoji; })
    );

    var groups = activeReactionGroups(sourceMessage);
    if (!groups.length) {
      reactionGrid.innerHTML = '<div class="chat-picker-empty">واکنش‌ها در این گفتگو غیرفعال هستند.</div>';
      if (reactionTabs) reactionTabs.innerHTML = "";
      return;
    }
    var selectedGroup = state.reactionCategory || "recent";
    if (!groups.some(function (group) { return group.id === selectedGroup; })) {
      selectedGroup = groups[0] ? groups[0].id : "all";
      state.reactionCategory = selectedGroup;
    }

    if (reactionTabs) {
      reactionTabs.innerHTML = "";
      groups.forEach(function (group) {
        var tab = document.createElement("button");
        tab.type = "button";
        tab.className = "reaction-picker-tab" + (group.id === selectedGroup ? " is-active" : "");
        tab.textContent = group.label;
        tab.addEventListener("click", function () {
          state.reactionCategory = group.id;
          renderReactionPicker();
        });
        reactionTabs.appendChild(tab);
      });
    }

    var baseGroup = groups.find(function (group) { return group.id === selectedGroup; }) || { emojis: REACTIONS.slice() };
    var list = baseGroup.emojis.filter(function (emoji) {
      return reactionAllowedInActiveConversation(emoji) && reactionMatchesQuery(emoji, query);
    });
    list = Array.from(new Set(list)).sort(function (left, right) {
      return reactionUsageScore(right) - reactionUsageScore(left);
    });

    if (!list.length) {
      reactionGrid.innerHTML = '<div class="chat-picker-empty">واکنش مطابق با جستجو پیدا نشد.</div>';
      return;
    }

    reactionGrid.innerHTML = "";
    list.forEach(function (emoji) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "reaction-picker-btn" + (ownReactions.has(emoji) ? " is-active" : "");
      button.textContent = emoji;
      button.addEventListener("click", function () {
        chooseReactionFromPicker(emoji);
      });
      reactionGrid.appendChild(button);
    });
  }

  function supportsNativeEmojiPicker() {
    return typeof window.EmojiPicker === "function";
  }

  async function openNativeEmojiPicker() {
    var message = reactionPickerSourceMessage();
    if (!message) return;
    if (!supportsNativeEmojiPicker()) {
      showToast("ایموجی سیستم در این مرورگر پشتیبانی نمی‌شود.");
      return;
    }

    try {
      if (!nativeEmojiPicker) {
        nativeEmojiPicker = new window.EmojiPicker({ locale: "fa" });
      }
      var picked = await nativeEmojiPicker.pick();
      var emoji = normalizeSpace(picked && (picked.emoji || picked.unicode || picked.character));
      if (!emoji) return;
      await chooseReactionFromPicker(emoji);
    } catch (error) {
      var code = normalizeSpace((error && (error.name || error.code)) || "");
      if (code === "AbortError") return;
      showToast("باز کردن ایموجی سیستم انجام نشد.");
    }
  }

  async function chooseReactionFromPicker(emoji) {
    var message = reactionPickerSourceMessage();
    if (!message) return;
    var ok = await toggleReaction(message, emoji);
    if (ok) {
      closeModal(true);
    } else {
      renderReactionPicker();
    }
  }

  function openReactionPicker(message) {
    if (!message || !reactionModal) {
      promptCustomReaction(message);
      return;
    }
    state.pendingReactionMessageId = message.id;
    state.reactionCategory = state.recentReactions.length ? "recent" : "all";
    if (reactionSearch) reactionSearch.value = "";
    if (reactionTabs) reactionTabs.innerHTML = "";
    if (reactionNativeWrap) {
      reactionNativeWrap.hidden = !supportsNativeEmojiPicker();
    }
    openModal(reactionModal, "reaction");
    renderReactionPicker();
    if (reactionSearch && !isMobileViewport()) {
      reactionSearch.focus({ preventScroll: true });
    }
  }

  function setPendingForwardMessages(messageIds) {
    var ids = (Array.isArray(messageIds) ? messageIds : [messageIds]).map(function (messageId) {
      return Math.max(0, Math.floor(toNumber(messageId, 0)));
    }).filter(function (messageId) {
      return messageId > 0;
    });
    ids = Array.from(new Set(ids));
    state.pendingForwardMessageIds = ids;
    state.pendingForwardMessageId = ids.length ? ids[0] : null;
  }

  function forwardSourceMessages() {
    var ids = Array.isArray(state.pendingForwardMessageIds) && state.pendingForwardMessageIds.length
      ? state.pendingForwardMessageIds
      : [state.pendingForwardMessageId];
    return ids.map(function (messageId) {
      return findMessage(messageId);
    }).filter(Boolean).sort(function (left, right) {
      return left.id - right.id;
    });
  }

  function renderForwardSourcePreview() {
    if (!forwardSourcePreview) return;
    var sourceMessages = forwardSourceMessages();
    if (!sourceMessages.length) {
      forwardSourcePreview.innerHTML = '<div class="chat-picker-empty">پیام مبدا پیدا نشد.</div>';
      return;
    }

    forwardSourcePreview.innerHTML = sourceMessages.map(function (message) {
      var meta = [];
      if (Array.isArray(message.attachments) && message.attachments.length) {
        meta.push(message.attachments.length === 1
          ? attachmentCategoryLabel(message.attachments[0].category || "file")
          : (message.attachments.length.toLocaleString("fa-IR") + " فایل"));
      }
      if (message.forwardedFrom && message.forwardedFrom.conversationTitle) {
        meta.push(message.forwardedFrom.conversationTitle);
      }
      return [
        '<article class="forward-source-card">',
        '  <span class="forward-source-card__kicker">' + escapeHtml(state.forwardIncludeSenderName ? (message.name || "کاربر") : "بدون نام فرستنده") + "</span>",
        '  <strong class="forward-source-card__preview">' + escapeHtml(messagePreviewText(message) || "پیام بدون متن") + "</strong>",
        meta.length ? ('  <small class="forward-source-card__meta">' + escapeHtml(meta.join(" • ")) + "</small>") : "",
        '</article>'
      ].join("");
    }).join("");
  }

  async function forwardMessageToConversation(targetConversationId) {
    var conversationId = normalizeSpace(targetConversationId);
    var sourceMessages = forwardSourceMessages();
    if (!conversationId || !sourceMessages.length) return;

    setModalBusy("forward", true);
    try {
      var response = await apiPost("forwardMessages", {
        conversationId: conversationId,
        sourceConversationId: normalizeSpace(state.activeConversationId),
        ids: sourceMessages.map(function (message) { return message.id; }).join(","),
        includeSenderName: state.forwardIncludeSenderName ? "1" : "0"
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "فوروارد پیام انجام نشد.");

      var nextConversations = Array.isArray(response.conversations)
        ? response.conversations.map(normalizeConversation).filter(Boolean)
        : [];
      if (nextConversations.length) {
        replaceConversations(nextConversations);
        renderConversationList();
      } else {
        var nextConversation = normalizeConversation(response.conversation);
        if (nextConversation) {
          upsertConversation(nextConversation);
          rebuildConversationsFromMap();
          renderConversationList();
        }
      }

      closeModal(true);
      await openConversation(conversationId, {
        forceFull: true,
        source: "forward"
      });
      if (state.messageSelectionMode) {
        exitMessageSelectionMode();
      }
      showToast(sourceMessages.length > 1 ? "پیام‌ها فوروارد شدند." : "پیام فوروارد شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : (sourceMessages.length > 1 ? "فوروارد پیام‌ها انجام نشد." : "فوروارد پیام انجام نشد."));
    } finally {
      setModalBusy("forward", false);
    }
  }

  function renderForwardList() {
    if (!forwardList) return;
    var sourceMessages = forwardSourceMessages();
    renderForwardSourcePreview();
    if (!sourceMessages.length) {
      forwardList.innerHTML = '<div class="chat-picker-empty">پیام مبدا پیدا نشد.</div>';
      return;
    }

    var query = normalizeSpace(forwardSearch && forwardSearch.value).toLowerCase();
    var targets = state.conversations.filter(function (conversation) {
      if (!conversation || conversation.id === state.activeConversationId) return false;
      var haystack = [
        conversation.title,
        conversation.subtitle,
        conversationPreview(conversation)
      ].join(" ").toLowerCase();
      return !query || haystack.indexOf(query) !== -1;
    });

    if (!targets.length) {
      forwardList.innerHTML = '<div class="chat-picker-empty">گفتگویی برای فوروارد پیدا نشد.</div>';
      return;
    }

    forwardList.innerHTML = "";
    targets.forEach(function (conversation) {
      var item = document.createElement("button");
      item.type = "button";
      item.className = "chat-picker-item";
      item.innerHTML = [
        '<span class="chat-picker-item__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(conversation.title)) + '</span></span>',
        '<span class="chat-picker-item__copy">',
        '  <strong>' + escapeHtml(conversation.title) + '</strong>',
        '  <span>' + escapeHtml(conversation.subtitle || conversationPreview(conversation)) + '</span>',
        '</span>',
        '<span class="chat-picker-check">‹</span>'
      ].join("");

      var avatar = item.querySelector(".chat-picker-item__avatar");
      var image = item.querySelector("img");
      var fallback = item.querySelector(".chat-picker-item__avatar span");
      renderAvatar(avatar, image, fallback, conversation.avatarUrl, conversation.title);

      item.addEventListener("click", function () {
        forwardMessageToConversation(conversation.id);
      });

      forwardList.appendChild(item);
    });
  }

  function openForwardPicker(message) {
    if (!message || !forwardModal) return;
    setPendingForwardMessages([message.id]);
    state.forwardIncludeSenderName = true;
    openModal(forwardModal, "forward");
    if (forwardIncludeSenderInput) {
      forwardIncludeSenderInput.checked = true;
    }
    if (forwardSearch) forwardSearch.value = "";
    renderForwardList();
    if (forwardSearch && !isMobileViewport()) {
      forwardSearch.focus({ preventScroll: true });
    }
  }

  function openForwardPickerForMessages(messageIds) {
    if (!forwardModal) return;
    setPendingForwardMessages(messageIds);
    state.forwardIncludeSenderName = true;
    openModal(forwardModal, "forward");
    if (forwardIncludeSenderInput) {
      forwardIncludeSenderInput.checked = true;
    }
    if (forwardSearch) forwardSearch.value = "";
    renderForwardList();
    if (forwardSearch && !isMobileViewport()) {
      forwardSearch.focus({ preventScroll: true });
    }
  }

  function autosizeComposer() {
    if (!chatTextEl) return;
    chatTextEl.style.height = "auto";
    var nextHeight = clamp(chatTextEl.scrollHeight, 38, isMobileViewport() ? 156 : 184);
    chatTextEl.style.height = nextHeight + "px";
    syncComposerDraftState();
  }

  function syncComposerDraftState() {
    if (!document.body) return;
    var hasText = !!(chatTextEl && normalizeSpace(chatTextEl.value));
    var hasAttachment = Array.isArray(state.pendingAttachments) && state.pendingAttachments.length > 0;
    document.body.classList.toggle("chat-composer-has-draft", hasText || hasAttachment);
  }

  function clearStreamRetryTimer() {
    if (state.streamRetryTimer) {
      window.clearTimeout(state.streamRetryTimer);
      state.streamRetryTimer = null;
    }
  }

  function clearStreamSyncTimer() {
    if (state.streamSyncTimer) {
      window.clearTimeout(state.streamSyncTimer);
      state.streamSyncTimer = null;
    }
  }

  function clearPresenceHeartbeatTimer() {
    if (state.presenceHeartbeatTimer) {
      window.clearTimeout(state.presenceHeartbeatTimer);
      state.presenceHeartbeatTimer = null;
    }
  }

  function clearTypingTimers() {
    if (state.typingRefreshTimer) {
      window.clearTimeout(state.typingRefreshTimer);
      state.typingRefreshTimer = null;
    }
    if (state.typingStopTimer) {
      window.clearTimeout(state.typingStopTimer);
      state.typingStopTimer = null;
    }
  }

  function configureTransport(transport) {
    var source = asObject(transport) || {};
    var mode = normalizeSpace(source.mode).toLowerCase();
    state.transportMode = mode === "sse" ? "sse" : "polling";
    state.transportStreamUrl = normalizeSpace(source.streamUrl);
    state.transportPresenceUrl = normalizeSpace(source.presenceUrl);
    var fallbackMs = source.fallbackIntervalMs != null ? source.fallbackIntervalMs : source.intervalMs;
    if (fallbackMs != null) {
      state.pollIntervalMs = clamp(toNumber(fallbackMs, 5000), MIN_POLL_MS, MAX_POLL_MS);
    }
  }

  function buildStreamContextUrl() {
    if (!state.transportStreamUrl) return "";
    try {
      var url = new URL(state.transportStreamUrl, window.location.origin);
      if (state.activeConversationId) {
        url.searchParams.set("conversationId", state.activeConversationId);
      } else {
        url.searchParams.delete("conversationId");
      }
      url.searchParams.set("includeMembers", state.infoSheetOpen ? "1" : "0");
      return url.toString();
    } catch (_error) {
      return "";
    }
  }

  function stopRealtimeStream(options) {
    var opts = asObject(options) || {};
    if (state.streamSource) {
      try {
        state.streamSource.close();
      } catch (_error) {
        // no-op
      }
    }
    state.streamSource = null;
    state.streamConnected = false;
    if (!opts.keepBinding) {
      state.streamBoundConversationId = "";
      state.streamBoundMembers = false;
    }
    if (!opts.keepRetry) {
      clearStreamRetryTimer();
    }
    clearStreamSyncTimer();
  }

  function queueRealtimeSync() {
    if (!state.me.loggedIn) return;
    if (state.streamSyncTimer) return;
    state.streamSyncTimer = window.setTimeout(function () {
      state.streamSyncTimer = null;
      syncConversation({
        forceFull: false,
        includeMembers: state.infoSheetOpen,
        silent: true
      }).catch(function () {});
    }, 120);
  }

  function scheduleRealtimeReconnect() {
    if (!state.me.loggedIn || state.transportMode !== "sse") return;
    if (state.streamRetryTimer) return;
    state.streamRetryTimer = window.setTimeout(function () {
      state.streamRetryTimer = null;
      startRealtimeStream();
    }, CHAT_STREAM_RETRY_MS);
  }

  function sendPresenceHeartbeat(options) {
    var opts = asObject(options) || {};
    if (!state.me.loggedIn) return Promise.resolve(null);
    var conversationId = normalizeSpace(opts.conversationId != null ? opts.conversationId : state.activeConversationId);
    var payload = {
      conversationId: conversationId,
      typing: opts.typing === true ? "1" : "0",
      activity: normalizeSpace(opts.activity) || (opts.typing === true ? "typing" : "active")
    };
    return apiPost("presencePing", payload, { quiet: true }).then(function (response) {
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      if (response && response.success === false && !response.networkError) {
        ensureSuccessResponse(response, "بروزرسانی وضعیت حضور انجام نشد.");
      }
      return response;
    }).catch(function () {
      return null;
    });
  }

  function schedulePresenceHeartbeat(delayMs) {
    clearPresenceHeartbeatTimer();
    if (!state.me.loggedIn || document.hidden) return;
    var wait = Math.max(0, Math.floor(toNumber(delayMs, CHAT_PRESENCE_HEARTBEAT_MS)));
    state.presenceHeartbeatTimer = window.setTimeout(function () {
      state.presenceHeartbeatTimer = null;
      var activeTyping = state.typingActive && normalizeSpace(state.typingConversationId) === normalizeSpace(state.activeConversationId);
      sendPresenceHeartbeat({
        conversationId: state.activeConversationId,
        typing: activeTyping,
        activity: activeTyping ? "typing" : "active"
      }).finally(function () {
        schedulePresenceHeartbeat(CHAT_PRESENCE_HEARTBEAT_MS);
      });
    }, wait);
  }

  function clearTypingActivity(skipNetwork) {
    var hadTyping = state.typingActive || !!state.typingConversationId;
    state.typingActive = false;
    state.typingConversationId = "";
    clearTypingTimers();
    if (hadTyping && !skipNetwork) {
      sendPresenceHeartbeat({
        conversationId: state.activeConversationId,
        typing: false,
        activity: "active"
      }).finally(function () {
        schedulePresenceHeartbeat(CHAT_PRESENCE_HEARTBEAT_MS);
      });
      return;
    }
    schedulePresenceHeartbeat(CHAT_PRESENCE_HEARTBEAT_MS);
  }

  function handleComposerTypingActivity() {
    var conversation = activeConversation();
    var text = normalizeSpace(chatTextEl && chatTextEl.value);
    if (!conversation || !text || document.hidden || !(conversation.permissions && conversation.permissions.canSend) || (conversation.settings && conversation.settings.muted)) {
      clearTypingActivity(false);
      return;
    }

    var alreadyTyping = state.typingActive && normalizeSpace(state.typingConversationId) === normalizeSpace(conversation.id);
    state.typingActive = true;
    state.typingConversationId = conversation.id;
    if (!alreadyTyping) {
      sendPresenceHeartbeat({
        conversationId: conversation.id,
        typing: true,
        activity: "typing"
      });
    }
    clearTypingTimers();
    state.typingRefreshTimer = window.setTimeout(function refreshTyping() {
      if (!state.typingActive || normalizeSpace(state.typingConversationId) !== normalizeSpace(conversation.id) || document.hidden) {
        return;
      }
      sendPresenceHeartbeat({
        conversationId: conversation.id,
        typing: true,
        activity: "typing"
      });
      state.typingRefreshTimer = window.setTimeout(refreshTyping, CHAT_TYPING_REFRESH_MS);
    }, CHAT_TYPING_REFRESH_MS);
    state.typingStopTimer = window.setTimeout(function () {
      clearTypingActivity(false);
    }, CHAT_TYPING_IDLE_MS);
  }

  function startRealtimeStream() {
    if (!state.me.loggedIn || state.transportMode !== "sse" || typeof window.EventSource !== "function") {
      return false;
    }
    var nextUrl = buildStreamContextUrl();
    if (!nextUrl) return false;
    var sameBinding = !!state.streamSource
      && state.streamBoundConversationId === normalizeSpace(state.activeConversationId)
      && state.streamBoundMembers === !!state.infoSheetOpen;
    if (sameBinding) {
      return true;
    }

    stopRealtimeStream({ keepRetry: false });

    try {
      var source = new window.EventSource(nextUrl);
      state.streamSource = source;
      state.streamBoundConversationId = normalizeSpace(state.activeConversationId);
      state.streamBoundMembers = !!state.infoSheetOpen;
      source.addEventListener("open", function () {
        if (state.streamSource !== source) return;
        state.streamConnected = true;
        clearStreamRetryTimer();
        stopPolling();
        setConnectionState("live", "متصل");
      });
      source.addEventListener("sync", function () {
        if (state.streamSource !== source) return;
        queueRealtimeSync();
      });
      source.addEventListener("presence", function (event) {
        if (state.streamSource !== source) return;
        var payload = null;
        try {
          payload = event && event.data ? JSON.parse(event.data) : null;
        } catch (_error) {
          payload = null;
        }
        if (payload) {
          applyPresenceBundle(payload);
        }
      });
      source.addEventListener("hello", function (event) {
        if (state.streamSource !== source) return;
        try {
          var payload = event && event.data ? JSON.parse(event.data) : null;
          if (payload && payload.mode === "sse") {
            setConnectionState("live", "متصل");
          }
        } catch (_error) {
          setConnectionState("live", "متصل");
        }
      });
      source.onerror = function () {
        if (state.streamSource !== source) return;
        stopRealtimeStream({ keepBinding: false, keepRetry: true });
        state.streamConnected = false;
        if (state.me.loggedIn) {
          setConnectionState("issue", "در انتظار اتصال زنده");
          startPolling();
          scheduleRealtimeReconnect();
        }
      };
      return true;
    } catch (_error) {
      state.streamConnected = false;
      return false;
    }
  }

  function refreshTransportBinding() {
    if (!state.me.loggedIn) return;
    if (state.transportMode === "sse" && typeof window.EventSource === "function") {
      if (!startRealtimeStream()) {
        startPolling();
      }
      return;
    }
    stopRealtimeStream();
    startPolling();
  }

  function stopPolling() {
    if (state.pollingTimer) {
      window.clearTimeout(state.pollingTimer);
    }
    state.pollingTimer = null;
    state.pollInFlight = false;
  }

  function startPolling() {
    stopPolling();
    if (!state.me.loggedIn) return;
    if (state.transportMode === "sse" && state.streamConnected) return;

    var scheduleNextPoll = function () {
      if (!state.me.loggedIn) {
        state.pollingTimer = null;
        return;
      }
      state.pollingTimer = window.setTimeout(function () {
        if (state.pollInFlight) {
          scheduleNextPoll();
          return;
        }
        state.pollInFlight = true;
        syncConversation({
          forceFull: false,
          includeMembers: state.infoSheetOpen,
          silent: true
        }).catch(function () {}).finally(function () {
          state.pollInFlight = false;
          scheduleNextPoll();
        });
      }, clamp(state.pollIntervalMs, MIN_POLL_MS, MAX_POLL_MS));
    };

    scheduleNextPoll();
  }

  function clearAutoReadTimer() {
    if (state.autoReadTimer) {
      window.clearTimeout(state.autoReadTimer);
      state.autoReadTimer = null;
    }
    state.autoReadConversationId = "";
    state.autoReadMessageId = 0;
  }

  function scheduleAutoMarkRead() {
    clearAutoReadTimer();
    var conversation = activeConversation();
    if (!conversation || !conversation.permissions || !conversation.permissions.canMarkRead) {
      return;
    }
    var targetMessageId = Math.max(0, Math.floor(toNumber(conversation.lastMessage && conversation.lastMessage.id, 0)));
    var currentRead = Math.max(0, Math.floor(toNumber(conversation.lastReadMessageId, 0)));
    if (targetMessageId <= 0 || targetMessageId <= currentRead) {
      return;
    }
    state.autoReadConversationId = conversation.id;
    state.autoReadMessageId = targetMessageId;
    state.autoReadTimer = window.setTimeout(function () {
      var conversationId = state.autoReadConversationId;
      var messageId = state.autoReadMessageId;
      clearAutoReadTimer();
      if (!conversationId || messageId <= 0) return;
      var latestConversation = state.conversationsById.get(conversationId);
      if (!latestConversation) return;
      var latestRead = Math.max(0, Math.floor(toNumber(latestConversation.lastReadMessageId, 0)));
      var latestLastMessageId = Math.max(0, Math.floor(toNumber(latestConversation.lastMessage && latestConversation.lastMessage.id, 0)));
      if (latestLastMessageId <= latestRead || latestLastMessageId !== messageId) return;
      setConversationReadStateById(conversationId, true, {
        silentToast: true,
        skipSync: true
      }).catch(function () {
        // no-op
      });
    }, 120);
  }

  async function syncConversation(options) {
    if (!state.me.loggedIn) return null;

    var opts = asObject(options) || {};
    var forceFull = !!opts.forceFull;
    var includeMembers = !!opts.includeMembers;
    var silent = !!opts.silent;
    var beforeId = Math.max(0, Math.floor(toNumber(opts.beforeId, 0)));
    var isOlderPage = beforeId > 0;
    var messageLimit = Math.max(0, Math.floor(toNumber(opts.messageLimit, forceFull ? INITIAL_MESSAGE_LIMIT : 0)));

    var requestedConversationId = normalizeSpace(
      opts.conversationId
      || opts.requestedConversationId
      || state.activeConversationId
      || state.initialConversationId
    );

    var requestPayload = {
      full: forceFull ? "1" : "0",
      sinceId: String(forceFull ? 0 : Math.max(0, state.lastMessageId)),
      includeMembers: includeMembers ? "1" : "0"
    };
    if (!forceFull && state.conversationListVersion) {
      requestPayload.conversationListVersion = state.conversationListVersion;
    }
    if (messageLimit > 0) {
      requestPayload.limit = String(messageLimit);
    }
    if (isOlderPage) {
      requestPayload.beforeId = String(beforeId);
    }
    if (requestedConversationId) {
      requestPayload.conversationId = requestedConversationId;
    }

    var token = ++state.requestToken;

    if (!silent) {
      setConnectionState(forceFull ? "sync" : "live", forceFull ? "در حال همگام‌سازی..." : "در حال دریافت...");
      setThreadUpdating(true);
    }

    try {
      var response = await apiGet("sync", requestPayload, { quiet: true });
      if (token !== state.requestToken) {
        return null;
      }

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }

      ensureSuccessResponse(response, "همگام‌سازی گفتگو انجام نشد.");

      var hadConnectionIssue = !!state.connectionIssue;
      state.connectionIssue = false;

      configureTransport(response && response.transport ? response.transport : {});

      if (typeof response.conversationListVersion === "string" && response.conversationListVersion) {
        state.conversationListVersion = response.conversationListVersion;
      }

      var hasConversationList = !!(response && Object.prototype.hasOwnProperty.call(response, "conversations"));
      var incomingConversations = (Array.isArray(response.conversations) ? response.conversations : [])
        .map(normalizeConversation)
        .filter(Boolean);
      var conversationMapChanged = false;
      if (hasConversationList) {
        state.conversationsById.clear();
        incomingConversations.forEach(function (conversation) {
          upsertConversation(conversation);
        });
        conversationMapChanged = true;
      }

      var currentPayloadConversation = normalizeConversation(response.conversation);
      if (currentPayloadConversation) {
        upsertConversation(currentPayloadConversation);
        conversationMapChanged = true;
      }

      if (conversationMapChanged) {
        state.conversations = sortConversations(Array.from(state.conversationsById.values()));
        scheduleFastChatCacheSave(180);
      } else if (!hasConversationList && state.conversations.length === 0 && state.conversationsById.size > 0) {
        state.conversations = sortConversations(Array.from(state.conversationsById.values()));
      }

      var previousActiveConversationId = state.activeConversationId;
      var nextConversationId = normalizeSpace(
        response.conversationId
        || (currentPayloadConversation && currentPayloadConversation.id)
        || requestedConversationId
        || state.activeConversationId
      );

      if (nextConversationId && state.conversationsById.has(nextConversationId)) {
        if (nextConversationId !== state.activeConversationId) {
          state.lastMessageId = 0;
        }
        state.activeConversationId = nextConversationId;
      } else if (!state.activeConversationId || !state.conversationsById.has(state.activeConversationId)) {
        state.activeConversationId = state.conversations.length ? state.conversations[0].id : "";
        state.lastMessageId = 0;
      }

      if (response && response.presence) {
        applyPresenceBundle(response.presence, { skipRender: true });
      }
      if (previousActiveConversationId !== state.activeConversationId) {
        pauseAllVoiceNotes();
        resetThreadSearchState({ keepPanel: state.threadSearchOpen, keepNavigator: false });
      }
      state.initialConversationId = "";

      var hasActiveConversation = !!state.activeConversationId;
      setThreadVisible(hasActiveConversation);
      renderConversationList();
      updateThreadHead();
      updateComposerState();
      updatePinnedUi();

      var active = activeConversation();
      updateMuteUi(active && active.settings ? active.settings : { muted: false });
      refreshTransportBinding();
      schedulePresenceHeartbeat(0);

      if (!hasActiveConversation) {
        clearThreadState();
        updateInfoSheet();
        updateFabVisibility();
        updateMobileNav();
        if (!silent || hadConnectionIssue) {
          setConnectionState("idle", "آفلاین");
        }
        updateComposerState();
        scheduleFastChatCacheSave(120);
        return response;
      }

      var normalizedMessages = (Array.isArray(response.messages) ? response.messages : [])
        .map(normalizeMessage)
        .filter(function (message) {
          if (!message) return false;
          return message.conversationId === state.activeConversationId;
        });
      var pendingInitialMessageId = 0;
      if (!isOlderPage && state.initialMessageId > 0 && requestedConversationId && requestedConversationId === state.activeConversationId) {
        pendingInitialMessageId = state.initialMessageId;
        state.initialMessageId = 0;
      }

      var page = asObject(response.messagePage) || {};
      if (isOlderPage || forceFull || Number(requestPayload.sinceId) <= 0) {
        state.hasMoreBefore = page.hasMoreBefore === true;
      }
      if (!isOlderPage && Object.prototype.hasOwnProperty.call(page, "firstUnreadMessageId")) {
        state.unreadDividerMessageId = Math.max(0, Math.floor(toNumber(page.firstUnreadMessageId, 0)));
      }

      appendMessages(normalizedMessages, {
        replaceAll: !isOlderPage && (forceFull || Number(requestPayload.sinceId) <= 0),
        prepend: isOlderPage,
        forceStick: !isOlderPage && (!!opts.forceStick || forceFull),
        smooth: !forceFull && !isOlderPage,
        markNew: !forceFull && !isOlderPage
      });
      if (!isOlderPage && !forceFull && Number(requestPayload.sinceId) > 0) {
        var hasImportantMention = normalizedMessages.some(function (message) {
          return !!(message && message.viewerImportantMentioned && message.studentNumber !== state.me.studentNumber);
        });
        if (hasImportantMention) {
          loadNotificationBadgeSummary(true);
        }
      }
      if (pendingInitialMessageId > 0) {
        window.requestAnimationFrame(function () {
          loadThreadContext(pendingInitialMessageId, {
            behavior: "smooth",
            block: "center",
            durationMs: 2200
          }).catch(function () {});
        });
      }

      updateInfoSheet();
      updateComposerState();
      updateThreadSearchUi();
      if (state.threadSearchOpen && state.threadNavigatorConversationId !== state.activeConversationId) {
        fetchThreadNavigator().catch(function () {});
      }
      if (!isOlderPage) {
        scheduleAutoMarkRead();
      }

      if (!silent || hadConnectionIssue) {
        setConnectionState("live", "متصل");
      }

      scheduleFastChatCacheSave(120);
      return response;
    } catch (error) {
      state.connectionIssue = true;
      updateComposerState();
      if (!silent) {
        setConnectionState("issue", "اختلال ارتباط");
        if (state.messages.size === 0) {
          showStreamState("error", "ارتباط با سرور قطع شد", "چند لحظه دیگر دوباره تلاش کن.");
        }
      } else {
        setConnectionState("issue", "در انتظار اتصال");
        if (state.messages.size === 0) {
          showStreamState("reconnect", "در حال برقراری اتصال مجدد", "ارتباط ناپایدار است، لطفاً چند لحظه صبر کن.");
        }
      }
      throw error;
    } finally {
      if (!silent) setThreadUpdating(false);
    }
  }

  async function openConversation(conversationId, options) {
    var nextId = normalizeSpace(conversationId);
    if (!nextId) return null;

    var opts = asObject(options) || {};
    var mobileView = normalizeSpace(opts.mobileView);
    var previousConversationId = state.activeConversationId;
    var changed = previousConversationId !== nextId;
    if (changed) {
      clearTypingActivity(false);
      saveComposerDraftNow(previousConversationId);
    }
    state.activeConversationId = nextId;

    if (changed) {
      clearAutoReadTimer();
      clearThreadState();
      state.lastMessageId = 0;
      state.oldestMessageId = 0;
      state.hasMoreBefore = false;
      state.olderMessagesLoading = false;
      state.threadAutoStick = true;
      clearReplyTarget();
      closeContextMenu();
      clearComposerAttachments();
      setUploadSheetOpen(false); setEmojiPanelOpen(false);
      resetVoiceRecorder();
      if (chatTextEl) {
        chatTextEl.value = "";
        autosizeComposer();
      }
      restoreComposerDraftForConversation(nextId, { preserveExisting: false });
      setComposerStatus("", "");
    }

    setThreadVisible(true);
    renderConversationList();
    updateThreadHead();
    updateComposerState();
    if (!changed && state.messages.size > 0) {
      scheduleFastChatCacheSave(140);
    }

    if (isMobileViewport() && !state.modalOpen) {
      setMobileView(mobileView === "list" ? "list" : "thread");
    }

    return syncConversation({
      forceFull: opts.forceFull !== false,
      includeMembers: state.infoSheetOpen,
      conversationId: nextId,
      silent: !!opts.silent,
      source: opts.source || ""
    });
  }

  async function handleUnauthorized(message) {
    stopPolling();
    closeContextMenu();
    closeInfoSheet();
    closeModal(true);
    resetLoggedOutUi(message || "نشست شما منقضی شده است.");

    var auth = safeAuthApi();
    if (auth && typeof auth.bootstrap === "function") {
      try {
        await auth.bootstrap(true);
      } catch (error) {
        // no-op
      }
    }
  }

  function resetLoggedOutUi(errorText) {
    document.body.classList.add("chat-auth-guard");
    document.body.classList.remove("chat-authenticated");

    state.me = {
      loggedIn: false,
      studentNumber: "",
      name: "",
      role: "student",
      roleLabel: "دانشجو",
      canModerateChat: false,
      isOwner: false,
      isRepresentative: false,
      profile: {
        avatarUrl: "",
        about: ""
      }
    };

    state.activeConversationId = "";
    state.conversations = [];
    state.conversationsById.clear();
    state.conversationListRenderKey = "";
    state.conversationListVersion = "";
    state.cacheHydrated = false;
    state.cacheHydratedAt = 0;
    state.conversationFilter = "";
    state.conversationListCategory = "all";
    state.messages.clear();
    invalidateMessageListCache();
    state.lastMessageId = 0;
    state.replyTargetId = null;
    state.directoryUsers = [];
    state.directoryLoaded = false;
    state.pendingDirectStart = false;
    state.pendingGroupCreate = false;
    state.pendingForwardMessageId = null;
    state.pendingForwardMessageIds = [];
    state.pendingReactionMessageId = null;
    state.pendingEditMessageId = null;
    state.confirmDialog = null;
    state.connectionIssue = false;
    state.showArchivedConversations = false;
    state.messageSelectionMode = false;
    state.selectedMessageIds.clear();
    state.threadAutoStick = true;
    state.pollDraftSelections = new Map();
    state.recentReactions = [];
    state.reactionUsage = new Map();
    state.reactionDetailsRequestToken += 1;
    state.pendingAttachments.forEach(releaseComposerAttachmentPreview);
    state.pendingAttachments = [];
    nativeEmojiPicker = null;

    stopPolling();
    stopRealtimeStream();
    clearPresenceHeartbeatTimer();
    clearTypingActivity(true);
    clearAutoReadTimer();
    clearFastChatCacheSaveHandle();
    resetVoiceRecorder();
    setUploadSheetOpen(false); setEmojiPanelOpen(false);
    renderComposerUploads();
    clearReplyTarget();
    closeMentionSuggestions();
    closeContextMenu();
    closeInfoSheet();
    closeModal(true);
    setThreadVisible(false);
    clearThreadState();
    renderConversationList();
    updateConversationFilterTabs();
    updateThreadHead();
    updateComposerState();
    updatePinnedUi();
    updateMuteUi({ muted: false });
    syncCurrentUserAvatar();

    hideAllStages();
    setHidden(bootBox, true);
    setHidden(loginBox, false);
    setHidden(chatBox, true);

    if (logoutBtn) logoutBtn.hidden = true;
    if (refreshBtn) refreshBtn.hidden = true;

    if (accountBtn) {
      var auth = safeAuthApi();
      if (auth && typeof auth.loginUrl === "function") {
        accountBtn.href = auth.loginUrl(window.location.pathname + window.location.search);
      } else {
        accountBtn.href = "/account/?returnTo=" + encodeURIComponent(window.location.pathname + window.location.search);
      }
    }

    showGuardMessage(
      errorText || "برای استفاده از پیام‌رسان، وارد حساب سایت شوید.",
      errorText ? "error" : ""
    );
    setConnectionState("idle", "آفلاین");
    setMobileView("list");
    updateFabVisibility();
    updateMobileNav();
    navBadgeState.notificationsUnread = 0;
    navBadgeState.notificationsPending = false;
    navBadgeState.notificationsLastUserKey = "";
    navBadgeState.notificationsLastFetchedAt = 0;
    syncMobileNavLinks();
    updateChatNavBadges();
    syncThemeColor();
  }

  async function applyAuthenticatedUser(rawUser) {
    var user = normalizeUser(rawUser);
    if (!user) {
      resetLoggedOutUi("اطلاعات هویتی حساب معتبر نیست.");
      return;
    }

    document.body.classList.remove("chat-auth-guard");
    document.body.classList.add("chat-authenticated");

    var userChanged = state.me.studentNumber !== user.studentNumber;
    state.me = Object.assign({ loggedIn: true }, user);
    loadReactionPreferences();

    if (userChanged) {
      state.activeConversationId = state.initialConversationId || "";
      state.conversations = [];
      state.conversationsById.clear();
      state.conversationListRenderKey = "";
      state.conversationListVersion = "";
      state.cacheHydrated = false;
      state.cacheHydratedAt = 0;
      state.conversationFilter = "";
      state.conversationListCategory = "all";
      state.messages.clear();
      invalidateMessageListCache();
      state.lastMessageId = 0;
      state.oldestMessageId = 0;
      state.hasMoreBefore = false;
      state.olderMessagesLoading = false;
      state.directoryUsers = [];
      state.directoryLoaded = false;
      state.pendingForwardMessageId = null;
      state.pendingReactionMessageId = null;
      state.pendingEditMessageId = null;
      state.confirmDialog = null;
      state.connectionIssue = false;
      state.showArchivedConversations = false;
      state.pollDraftSelections = new Map();
      state.reactionDetailsRequestToken += 1;
      state.pendingAttachments.forEach(releaseComposerAttachmentPreview);
      state.pendingAttachments = [];
      clearFastChatCacheSaveHandle();
      clearAutoReadTimer();
      stopRealtimeStream();
      clearPresenceHeartbeatTimer();
      clearTypingActivity(true);
      setUploadSheetOpen(false); setEmojiPanelOpen(false);
      renderComposerUploads();
      resetVoiceRecorder();
      clearReplyTarget();
      closeContextMenu();
      closeInfoSheet();
      closeModal(true);
    }

    hideAllStages();
    setHidden(bootBox, true);
    setHidden(loginBox, true);
    setHidden(chatBox, false);
    showGuardMessage("", "");

    if (logoutBtn) logoutBtn.hidden = false;
    if (refreshBtn) refreshBtn.hidden = false;
    if (accountBtn) accountBtn.href = "/account/";
    syncMobileNavLinks();
    updateChatNavBadges();
    loadNotificationBadgeSummary(true);

    syncCurrentUserAvatar();
    updateMobileNav();
    updateConversationFilterTabs();

    var hydratedFromCache = hydrateFastChatCache();
    if (hydratedFromCache) {
      await yieldChatFrame();
    }

    try {
      await syncConversation({
        forceFull: !hydratedFromCache,
        includeMembers: state.infoSheetOpen,
        conversationId: state.activeConversationId || state.initialConversationId,
        silent: false
      });
    } catch (error) {
      showToast(error && error.message ? error.message : "بارگذاری گفتگوها انجام نشد.");
    }

    refreshTransportBinding();
    schedulePresenceHeartbeat(0);
  }

  async function handleAuthChange(detail) {
    var payload = asObject(detail) || {};
    var status = normalizeSpace(payload.status || "");

    if (status === "session-restoring" || status === "logging-out" || status === "logging-in") {
      hideAllStages();
      setHidden(loginBox, true);
      setHidden(chatBox, true);
      setHidden(bootBox, false);
      if (status === "logging-out") {
        setBootState("در حال خروج از حساب...");
      } else if (status === "logging-in") {
        setBootState("در حال ورود به حساب...");
      } else {
        setBootState("در حال بازیابی نشست...");
      }
      return;
    }

    if (!payload.loggedIn || !payload.user) {
      resetLoggedOutUi(payload.error || "");
      return;
    }

    await applyAuthenticatedUser(payload.user);
  }

  function nextComposerAttachmentId() {
    return "upl-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 7);
  }

  function uploadedComposerItems() {
    return state.pendingAttachments.filter(function (item) {
      return item && item.status === "uploaded" && item.attachment && item.attachment.id;
    });
  }

  function composerHasUploadingItems() {
    return state.pendingAttachments.some(function (item) {
      return !!item && item.status === "uploading";
    });
  }

  function composerPreviewKindFromFile(file) {
    var mime = normalizeSpace(file && file.type).toLowerCase();
    if (mime.indexOf("image/") === 0) return "image";
    if (mime.indexOf("video/") === 0) return "video";
    return "";
  }

  function releaseComposerAttachmentPreview(item) {
    if (!item || !item.previewOwned || !item.previewUrl || item.previewReleased) return;
    if (typeof URL !== "undefined" && typeof URL.revokeObjectURL === "function") {
      try {
        URL.revokeObjectURL(item.previewUrl);
      } catch (_error) {}
    }
    item.previewReleased = true;
  }

  function composerAttachmentPreviewPayload(item) {
    var source = asObject(item) || {};
    var attachment = source.attachment || null;
    var previewKind = normalizeSpace(source.previewKind);
    if (!previewKind && attachment && isVisualAttachment(attachment)) {
      previewKind = attachment.category === "video" ? "video" : "image";
    }
    if (previewKind !== "image" && previewKind !== "video") return null;
    var previewUrl = toText(source.previewUrl || (attachment && (attachment.previewUrl || attachment.url)) || "");
    var mediaUrl = toText((attachment && attachment.url) || previewUrl);
    if (!previewUrl || !mediaUrl) return null;
    return {
      kind: previewKind,
      previewUrl: previewUrl,
      mediaUrl: mediaUrl,
      poster: previewKind === "video" ? previewUrl : "",
      caption: normalizeSpace(source.name || (attachment && attachment.name) || "رسانه")
    };
  }

  function moveComposerAttachment(localId, offset) {
    var id = normalizeSpace(localId);
    if (!id) return;
    if (!offset) return;
    var direction = offset < 0 ? -1 : 1;
    var currentIndex = state.pendingAttachments.findIndex(function (item) {
      return item && item.localId === id;
    });
    if (currentIndex < 0) return;
    var nextIndex = clamp(currentIndex + direction, 0, Math.max(0, state.pendingAttachments.length - 1));
    if (nextIndex === currentIndex) return;
    var reordered = state.pendingAttachments.slice();
    var moved = reordered.splice(currentIndex, 1)[0];
    reordered.splice(nextIndex, 0, moved);
    state.pendingAttachments = reordered;
    renderComposerUploads();
    updateComposerState();
  }

  function removeComposerAttachmentByLocalId(localId) {
    var id = normalizeSpace(localId);
    if (!id) return;
    state.pendingAttachments = state.pendingAttachments.filter(function (item) {
      if (!item || item.localId !== id) return true;
      if (item.status === "uploading" && item.xhr && typeof item.xhr.abort === "function") {
        try {
          item.xhr.abort();
        } catch (_error) {
          // Ignore abort failures.
        }
      }
      releaseComposerAttachmentPreview(item);
      return false;
    });
    renderComposerUploads();
    updateComposerState();
  }

  function clearComposerAttachments(attachmentIds) {
    var ids = Array.isArray(attachmentIds)
      ? attachmentIds.map(function (value) { return normalizeSpace(value); }).filter(Boolean)
      : [];
    if (!ids.length) {
      state.pendingAttachments.forEach(releaseComposerAttachmentPreview);
      state.pendingAttachments = [];
      renderComposerUploads();
      return;
    }
    var idSet = new Set(ids);
    state.pendingAttachments = state.pendingAttachments.filter(function (item) {
      var attachmentId = normalizeSpace(item && item.attachment && item.attachment.id);
      if (!attachmentId || !idSet.has(attachmentId)) return true;
      releaseComposerAttachmentPreview(item);
      return false;
    });
    renderComposerUploads();
  }

  function renderComposerUploads() {
    if (!composerUploads) return;
    if (!state.pendingAttachments.length) {
      composerUploads.hidden = true;
      composerUploads.innerHTML = "";
      syncComposerDraftState();
      return;
    }

    composerUploads.hidden = false;
    syncComposerDraftState();
    composerUploads.innerHTML = state.pendingAttachments.map(function (item, index) {
      var statusLabel = "در حال ارسال";
      if (item.status === "uploaded") {
        statusLabel = "آماده ارسال";
      } else if (item.status === "error") {
        statusLabel = item.error || "خطا در بارگذاری";
      }
      var progressWidth = clamp(toNumber(item.progress, 0), 0, 100);
      var preview = composerAttachmentPreviewPayload(item);
      var canMoveUp = index > 0;
      var canMoveDown = index < (state.pendingAttachments.length - 1);
      return [
        '<article class="composer-upload-item" data-upl-id="' + escapeHtml(item.localId) + '">',
        preview
          ? (
            '  <button type="button" class="composer-upload-item__preview" data-upl-preview="' + escapeHtml(item.localId) + '" data-media-kind="' + escapeHtml(preview.kind) + '" data-media-src="' + escapeHtml(preview.mediaUrl) + '"' + (preview.poster ? ' data-media-poster="' + escapeHtml(preview.poster) + '"' : "") + ' data-media-caption="' + escapeHtml(preview.caption) + '" data-media-group="composer-pending" aria-label="پیش‌نمایش فایل">' +
            (preview.kind === "video"
              ? '<video playsinline muted preload="metadata" src="' + escapeHtml(preview.mediaUrl) + '" poster="' + escapeHtml(preview.poster) + '"></video><span class="msg-media-preview__play" aria-hidden="true"></span>'
              : '<img src="' + escapeHtml(preview.previewUrl) + '" alt="' + escapeHtml(preview.caption) + '" loading="lazy">') +
            '    <span class="composer-upload-item__order" aria-hidden="true">' + (index + 1).toLocaleString("fa-IR") + "</span>" +
            "  </button>"
          )
          : (
            '  <div class="composer-upload-item__preview composer-upload-item__preview--file" aria-hidden="true">' +
            '    <span class="composer-upload-item__preview-icon">' + escapeHtml((item.status === "error" ? "!" : "FILE")) + '</span>' +
            '    <span class="composer-upload-item__order">' + (index + 1).toLocaleString("fa-IR") + "</span>" +
            "  </div>"
          ),
        '  <div class="composer-upload-item__copy">',
        '    <div class="composer-upload-item__name" title="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + "</div>",
        '    <div class="composer-upload-item__meta">' + escapeHtml(statusLabel) + ' • ' + escapeHtml(formatFileSize(item.sizeBytes || 0)) + "</div>",
        item.status === "uploading"
          ? ('    <div class="composer-upload-item__progress"><span style="width:' + progressWidth.toFixed(1) + '%"></span></div>')
          : "",
        "  </div>",
        '  <div class="composer-upload-item__actions">',
        (preview && preview.kind === "image" && item.status !== "uploading"
          ? ('    <button type="button" class="composer-upload-item__edit" data-upl-edit="' + escapeHtml(item.localId) + '" aria-label="ویرایش تصویر">' +
            '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20L4.7 16.6C4.85 15.86 5.21 15.18 5.74 14.65L15.6 4.79C16.39 4 17.66 4 18.45 4.79L19.21 5.55C20 6.34 20 7.61 19.21 8.4L9.35 18.26C8.82 18.79 8.14 19.15 7.4 19.3L4 20Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 6.5L17.5 10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>' +
            "</button>")
          : ""),
        '    <button type="button" class="composer-upload-item__move" data-upl-move="' + escapeHtml(item.localId) + '" data-upl-shift="-1"' + (canMoveUp ? ' aria-label="انتقال به بالا"' : ' disabled aria-label="آیتم اول"') + ">↑</button>",
        '    <button type="button" class="composer-upload-item__move" data-upl-move="' + escapeHtml(item.localId) + '" data-upl-shift="1"' + (canMoveDown ? ' aria-label="انتقال به پایین"' : ' disabled aria-label="آیتم آخر"') + ">↓</button>",
        '    <button type="button" class="composer-upload-item__remove" data-upl-remove="' + escapeHtml(item.localId) + '"' + (item.status === "uploading" ? ' disabled aria-label="در حال بارگذاری"' : ' aria-label="حذف"') + ">×</button>",
        "  </div>",
        "</article>"
      ].join("");
    }).join("");
  }

  function setUploadSheetOpen(open) {
    if (!composerUploadSheet || !attachBtn) return;
    var shouldOpen = !!open;
    composerUploadSheet.hidden = !shouldOpen;
    attachBtn.classList.toggle("is-open", shouldOpen);
    if (shouldOpen) {
      setEmojiPanelOpen(false);
    }
  }

  function renderEmojiTabs() {
    if (!emojiTabs) return;
    emojiTabs.innerHTML = EMOJI_CATEGORIES.map(function (category) {
      var active = category.key === state.emojiCategory;
      return '<button type="button" class="composer-emoji-tab' + (active ? " is-active" : "") + '" data-emoji-category="' + escapeHtml(category.key) + '" aria-label="' + escapeHtml(category.label) + '">' + category.icon + "</button>";
    }).join("");
  }

  function renderEmojiGrid() {
    if (!emojiGrid) return;
    var category = EMOJI_CATEGORIES.find(function (item) {
      return item.key === state.emojiCategory;
    }) || EMOJI_CATEGORIES[0];
    emojiGrid.innerHTML = category.items.map(function (emoji) {
      return '<button type="button" class="composer-emoji-item" data-emoji="' + escapeHtml(emoji) + '">' + emoji + "</button>";
    }).join("");
  }

  function setEmojiCategory(key) {
    if (!EMOJI_CATEGORIES.some(function (item) { return item.key === key; })) return;
    if (state.emojiCategory === key) return;
    state.emojiCategory = key;
    renderEmojiTabs();
    renderEmojiGrid();
  }

  function setEmojiPanelOpen(open) {
    if (!emojiPanel || !emojiBtn) return;
    var shouldOpen = !!open;
    emojiPanel.hidden = !shouldOpen;
    emojiBtn.classList.toggle("is-open", shouldOpen);
  }

  function insertEmojiAtCursor(emoji) {
    if (!chatTextEl || chatTextEl.disabled || !emoji) return;
    var value = toText(chatTextEl.value);
    var start = chatTextEl.selectionStart != null ? chatTextEl.selectionStart : value.length;
    var end = chatTextEl.selectionEnd != null ? chatTextEl.selectionEnd : value.length;
    chatTextEl.value = value.slice(0, start) + emoji + value.slice(end);
    var nextCaret = start + emoji.length;
    chatTextEl.setSelectionRange(nextCaret, nextCaret);
    autosizeComposer();
    handleComposerTypingActivity();
    chatTextEl.focus({ preventScroll: true });
  }

  function parseUploadResponse(xhr) {
    var payload = null;
    var raw = toText(xhr.responseText || "");
    if (raw) {
      try {
        payload = JSON.parse(raw);
      } catch (_error) {
        payload = null;
      }
    }
    if (!payload || typeof payload !== "object") {
      payload = {
        success: false,
        error: xhr.status >= 500 ? "خطای داخلی سرور رخ داد." : "پاسخ نامعتبر از سرور دریافت شد."
      };
    }
    payload.httpStatus = xhr.status;
    return payload;
  }

  function uploadAttachmentRequest(file, conversationId, options, onProgress) {
    var opts = asObject(options) || {};
    return new Promise(function (resolve) {
      var xhr = new XMLHttpRequest();
      var form = new FormData();
      form.append("action", "uploadAttachment");
      form.append("cohort", pageCohort);
      form.append("conversationId", conversationId);
      form.append("file", file, file.name || "file");
      if (opts.isVoice) {
        form.append("isVoice", "1");
      }
      if (opts.durationSeconds != null) {
        form.append("durationSeconds", String(Math.max(0, toNumber(opts.durationSeconds, 0))));
      }

      xhr.open("POST", "/chat/chat_api.php", true);
      xhr.withCredentials = true;
      xhr.setRequestHeader("Accept", "application/json");

      xhr.upload.onprogress = function (event) {
        if (!event || !event.lengthComputable || typeof onProgress !== "function") return;
        var percent = event.total > 0 ? (event.loaded / event.total) * 100 : 0;
        onProgress(percent);
      };

      xhr.onload = function () {
        resolve({ response: parseUploadResponse(xhr), xhr: xhr });
      };
      xhr.onerror = function () {
        resolve({
          response: {
            success: false,
            error: "ارتباط با سرور برقرار نشد.",
            networkError: true,
            httpStatus: 0
          },
          xhr: xhr
        });
      };
      xhr.onabort = function () {
        resolve({
          response: {
            success: false,
            error: "بارگذاری لغو شد.",
            aborted: true,
            httpStatus: 0
          },
          xhr: xhr
        });
      };
      xhr.send(form);
    });
  }

  async function queueAttachmentUpload(file, options) {
    var conversation = activeConversation();
    var opts = asObject(options) || {};
    if (!conversation) {
      showToast("ابتدا یک گفت‌وگو را انتخاب کن.");
      return null;
    }
    if (!conversation.permissions || conversation.permissions.canSend === false) {
      showToast("در این گفت‌وگو اجازه ارسال فایل ندارید.");
      return null;
    }
    if (!file) return null;

    if (file.size > MAX_MEDIA_BYTES) {
      showToast("حجم فایل از سقف مجاز بیشتر است.");
      return null;
    }

    var localId = nextComposerAttachmentId();
    var item = {
      localId: localId,
      name: normalizeSpace(file.name) || (opts.isVoice ? "voice-note" : "file"),
      sizeBytes: Math.max(0, Math.floor(toNumber(file.size, 0))),
      status: "uploading",
      progress: 0,
      attachment: null,
      error: "",
      xhr: null,
      previewKind: composerPreviewKindFromFile(file),
      previewUrl: "",
      previewOwned: false,
      previewReleased: false
    };
    if (item.previewKind && typeof URL !== "undefined" && typeof URL.createObjectURL === "function") {
      try {
        item.previewUrl = URL.createObjectURL(file);
        item.previewOwned = true;
      } catch (_error) {
        item.previewUrl = "";
        item.previewOwned = false;
      }
    }
    state.pendingAttachments.push(item);
    renderComposerUploads();
    updateComposerState();

    var uploadResult = await uploadAttachmentRequest(file, conversation.id, opts, function (progress) {
      item.progress = progress;
      renderComposerUploads();
    });
    item.xhr = uploadResult.xhr;

    var response = uploadResult.response;
    if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
      item.status = "error";
      item.error = "نشست شما منقضی شده است.";
      renderComposerUploads();
      updateComposerState();
      return null;
    }

    if (!response || !response.success || !response.attachment) {
      item.status = "error";
      item.error = (response && response.error) || "بارگذاری فایل انجام نشد.";
      renderComposerUploads();
      updateComposerState();
      return null;
    }

    item.status = "uploaded";
    item.progress = 100;
    item.error = "";
    item.attachment = normalizeAttachment(response.attachment);
    renderComposerUploads();
    if (response.notice) {
      setComposerStatus(response.notice, "");
    }
    updateComposerState();
    return item.attachment;
  }

  function pickAttachmentFiles(accept, label) {
    if (!attachmentInput) return;
    attachmentInput.accept = accept || "*/*";
    attachmentInput.dataset.attachLabel = label || "فایل";
    attachmentInput.click();
  }

  function canQueueAttachments() {
    return !!(attachmentInput && !attachmentInput.disabled);
  }

  function imageEditorCanvasDisplayRect() {
    var canvasRect = imageEditorCanvas.getBoundingClientRect();
    var stageRect = imageEditorStage.getBoundingClientRect();
    return {
      left: canvasRect.left - stageRect.left,
      top: canvasRect.top - stageRect.top,
      width: canvasRect.width,
      height: canvasRect.height
    };
  }

  function imageEditorCropRectForHandle(startRect, handleName, dx, dy, maxW, maxH, minSize) {
    var x = startRect.x;
    var y = startRect.y;
    var w = startRect.w;
    var h = startRect.h;
    if (handleName.indexOf("w") !== -1) {
      var newX = clamp(startRect.x + dx, 0, startRect.x + startRect.w - minSize);
      w = startRect.w + (startRect.x - newX);
      x = newX;
    } else if (handleName.indexOf("e") !== -1) {
      w = clamp(startRect.w + dx, minSize, maxW - startRect.x);
    }
    if (handleName.indexOf("n") !== -1) {
      var newY = clamp(startRect.y + dy, 0, startRect.y + startRect.h - minSize);
      h = startRect.h + (startRect.y - newY);
      y = newY;
    } else if (handleName.indexOf("s") !== -1) {
      h = clamp(startRect.h + dy, minSize, maxH - startRect.y);
    }
    return { x: x, y: y, w: w, h: h };
  }

  function updateImageEditorCropBoxStyle() {
    if (!imageEditorState || !imageEditorCropBox || imageEditorState.mode !== "crop") return;
    var source = imageEditorState.source;
    var displayRect = imageEditorCanvasDisplayRect();
    if (!displayRect.width || !displayRect.height) return;
    var scaleX = displayRect.width / source.width;
    var scaleY = displayRect.height / source.height;
    var rect = imageEditorState.cropRect;
    imageEditorCropBox.style.left = (displayRect.left + rect.x * scaleX) + "px";
    imageEditorCropBox.style.top = (displayRect.top + rect.y * scaleY) + "px";
    imageEditorCropBox.style.width = (rect.w * scaleX) + "px";
    imageEditorCropBox.style.height = (rect.h * scaleY) + "px";
  }

  function initImageEditorCropBox() {
    if (!imageEditorState) return;
    if (!imageEditorState.cropRect) {
      var source = imageEditorState.source;
      imageEditorState.cropRect = { x: 0, y: 0, w: source.width, h: source.height };
    }
    requestAnimationFrame(updateImageEditorCropBoxStyle);
  }

  function applyImageEditorCrop() {
    if (!imageEditorState || !imageEditorState.cropRect) return;
    var rect = imageEditorState.cropRect;
    var x = Math.round(rect.x);
    var y = Math.round(rect.y);
    var w = Math.round(rect.w);
    var h = Math.round(rect.h);
    imageEditorState.cropRect = null;
    if (w < 1 || h < 1) return;
    if (x === 0 && y === 0 && w === imageEditorState.source.width && h === imageEditorState.source.height) return;
    var cropped = document.createElement("canvas");
    cropped.width = w;
    cropped.height = h;
    cropped.getContext("2d").drawImage(imageEditorState.source, x, y, w, h, 0, 0, w, h);
    imageEditorState.source = cropped;
    renderImageEditorCanvas();
  }

  function renderImageEditorCanvas() {
    if (!imageEditorState || !imageEditorCanvas) return;
    var source = imageEditorState.source;
    if (imageEditorCanvas.width !== source.width || imageEditorCanvas.height !== source.height) {
      imageEditorCanvas.width = source.width;
      imageEditorCanvas.height = source.height;
    }
    var ctx = imageEditorCanvas.getContext("2d");
    ctx.save();
    ctx.clearRect(0, 0, imageEditorCanvas.width, imageEditorCanvas.height);
    ctx.filter = IMAGE_EDITOR_FILTERS[imageEditorState.filterIndex].css;
    ctx.drawImage(source, 0, 0);
    ctx.restore();
    if (imageEditorState.mode === "crop") {
      requestAnimationFrame(updateImageEditorCropBoxStyle);
    }
  }

  function rotateImageEditorImage() {
    if (!imageEditorState) return;
    if (imageEditorState.mode === "crop") {
      applyImageEditorCrop();
    }
    var source = imageEditorState.source;
    var rotated = document.createElement("canvas");
    rotated.width = source.height;
    rotated.height = source.width;
    var ctx = rotated.getContext("2d");
    ctx.translate(rotated.width / 2, rotated.height / 2);
    ctx.rotate(Math.PI / 2);
    ctx.drawImage(source, -source.width / 2, -source.height / 2);
    imageEditorState.source = rotated;
    renderImageEditorCanvas();
    if (imageEditorState.mode === "crop") {
      initImageEditorCropBox();
    }
  }

  function updateImageEditorFilterLabel() {
    if (!imageEditorFilterLabel || !imageEditorState) return;
    imageEditorFilterLabel.textContent = IMAGE_EDITOR_FILTERS[imageEditorState.filterIndex].label;
  }

  function cycleImageEditorFilter() {
    if (!imageEditorState) return;
    imageEditorState.filterIndex = (imageEditorState.filterIndex + 1) % IMAGE_EDITOR_FILTERS.length;
    updateImageEditorFilterLabel();
    renderImageEditorCanvas();
  }

  function setImageEditorMode(mode) {
    if (!imageEditorState) return;
    var current = imageEditorState.mode;
    if (current === "crop" && mode !== "crop") {
      applyImageEditorCrop();
    }
    var next = current === mode ? "view" : mode;
    imageEditorState.mode = next;
    if (imageEditorCrop) imageEditorCrop.classList.toggle("is-active", next === "crop");
    if (imageEditorDraw) imageEditorDraw.classList.toggle("is-active", next === "draw");
    if (imageEditorCropBox) imageEditorCropBox.hidden = next !== "crop";
    if (imageEditorDrawOptions) imageEditorDrawOptions.hidden = next !== "draw";
    if (next === "crop") {
      initImageEditorCropBox();
    }
  }

  function imageEditorCanvasPoint(event) {
    var rect = imageEditorCanvas.getBoundingClientRect();
    var scaleX = imageEditorCanvas.width / rect.width;
    var scaleY = imageEditorCanvas.height / rect.height;
    return {
      x: (event.clientX - rect.left) * scaleX,
      y: (event.clientY - rect.top) * scaleY
    };
  }

  function drawImageEditorStroke(from, to) {
    if (!imageEditorState) return;
    var source = imageEditorState.source;
    var ctx = source.getContext("2d");
    ctx.save();
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.strokeStyle = imageEditorState.drawColor;
    ctx.lineWidth = Math.max(2, Math.round(Math.max(source.width, source.height) * 0.01));
    ctx.beginPath();
    ctx.moveTo(from.x, from.y);
    ctx.lineTo(to.x, to.y);
    ctx.stroke();
    ctx.restore();
    renderImageEditorCanvas();
  }

  function openImageEditor(localId) {
    var id = normalizeSpace(localId);
    if (!id || !imageEditor || !imageEditorCanvas) return;
    var item = state.pendingAttachments.find(function (entry) {
      return entry && entry.localId === id;
    });
    if (!item) return;
    var preview = composerAttachmentPreviewPayload(item);
    if (!preview || preview.kind !== "image") return;

    var image = new Image();
    image.onload = function () {
      var source = document.createElement("canvas");
      source.width = image.naturalWidth || image.width;
      source.height = image.naturalHeight || image.height;
      source.getContext("2d").drawImage(image, 0, 0);

      imageEditorState = {
        localId: id,
        itemName: item.name,
        source: source,
        filterIndex: 0,
        mode: "view",
        drawColor: "#ff3b30",
        cropRect: null,
        cropDrag: null,
        drawPointerId: null,
        lastDrawPoint: null
      };

      if (imageEditorCrop) imageEditorCrop.classList.remove("is-active");
      if (imageEditorDraw) imageEditorDraw.classList.remove("is-active");
      if (imageEditorCropBox) imageEditorCropBox.hidden = true;
      if (imageEditorDrawOptions) imageEditorDrawOptions.hidden = true;
      updateImageEditorFilterLabel();
      renderImageEditorCanvas();
      imageEditor.hidden = false;
    };
    image.onerror = function () {
      showToast("بارگذاری تصویر برای ویرایش انجام نشد.");
    };
    image.src = preview.previewUrl;
  }

  function closeImageEditor() {
    if (!imageEditor) return;
    imageEditor.hidden = true;
    if (imageEditorCropBox) imageEditorCropBox.hidden = true;
    if (imageEditorDrawOptions) imageEditorDrawOptions.hidden = true;
    if (imageEditorCrop) imageEditorCrop.classList.remove("is-active");
    if (imageEditorDraw) imageEditorDraw.classList.remove("is-active");
    imageEditorState = null;
  }

  function finishImageEditor() {
    if (!imageEditorState) return;
    if (imageEditorState.mode === "crop") {
      applyImageEditorCrop();
    }
    var source = imageEditorState.source;
    var localId = imageEditorState.localId;
    var baseName = normalizeSpace(imageEditorState.itemName).replace(/\.[^.]+$/, "") || "image";
    var filterCss = IMAGE_EDITOR_FILTERS[imageEditorState.filterIndex].css;
    var output = source;
    if (filterCss !== "none") {
      output = document.createElement("canvas");
      output.width = source.width;
      output.height = source.height;
      var outCtx = output.getContext("2d");
      outCtx.filter = filterCss;
      outCtx.drawImage(source, 0, 0);
    }
    output.toBlob(function (blob) {
      if (!blob) {
        showToast("ذخیره تصویر ویرایش‌شده انجام نشد.");
        return;
      }
      var file = new File([blob], baseName + "-edited.png", { type: "image/png" });
      removeComposerAttachmentByLocalId(localId);
      queueAttachmentUpload(file, {}).catch(function () {});
      closeImageEditor();
    }, "image/png");
  }

  function bestVoiceMimeType() {
    if (typeof window.MediaRecorder !== "function" || typeof window.MediaRecorder.isTypeSupported !== "function") {
      return "";
    }
    for (var i = 0; i < VOICE_MIME_CANDIDATES.length; i += 1) {
      if (window.MediaRecorder.isTypeSupported(VOICE_MIME_CANDIDATES[i])) {
        return VOICE_MIME_CANDIDATES[i];
      }
    }
    return "";
  }

  function voiceFileExtension(mimeType) {
    var mime = normalizeSpace(mimeType).toLowerCase();
    if (mime.indexOf("ogg") !== -1) return "ogg";
    if (mime.indexOf("mp4") !== -1 || mime.indexOf("m4a") !== -1) return "m4a";
    return "webm";
  }

  function renderComposerVoiceWave(samples) {
    if (!composerVoiceWave) return;
    var normalized = Array.isArray(samples) && samples.length
      ? samples.map(function (sample) {
          return clamp(toNumber(sample, 0.2), 0.16, 1);
        })
      : seededWaveformSamples("idle-voice", VOICE_WAVE_BAR_COUNT).map(function (sample) {
          return clamp(sample * 0.42, 0.16, 0.48);
        });
    if (composerVoiceWave.childElementCount !== normalized.length) {
      composerVoiceWave.innerHTML = renderWaveBarsMarkup(normalized, "composer-voice__bar");
      return;
    }
    Array.from(composerVoiceWave.children).forEach(function (bar, index) {
      var height = Math.round(normalized[index] * 1000) / 10;
      bar.style.setProperty("--voice-bar-height", height + "%");
    });
  }

  function stopVoiceMonitoring(recorder) {
    if (!recorder) return;
    if (recorder.waveRaf) {
      window.cancelAnimationFrame(recorder.waveRaf);
      recorder.waveRaf = 0;
    }
    if (recorder.sourceNode && typeof recorder.sourceNode.disconnect === "function") {
      try {
        recorder.sourceNode.disconnect();
      } catch (_error) {}
    }
    if (recorder.analyser && typeof recorder.analyser.disconnect === "function") {
      try {
        recorder.analyser.disconnect();
      } catch (_error) {}
    }
    if (recorder.audioContext && typeof recorder.audioContext.close === "function") {
      try {
        recorder.audioContext.close().catch(function () {});
      } catch (_error) {}
    }
    recorder.audioContext = null;
    recorder.analyser = null;
    recorder.analyserData = null;
    recorder.sourceNode = null;
  }

  function startVoiceMonitoring(recorder) {
    if (!recorder) return;
    recorder.waveSamples = seededWaveformSamples("live-" + recorder.startedAt, VOICE_WAVE_BAR_COUNT).map(function (sample) {
      return clamp(sample * 0.5, 0.16, 0.54);
    });
    renderComposerVoiceWave(recorder.waveSamples);
    var AudioContextCtor = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextCtor || !recorder.stream) {
      return;
    }
    try {
      var audioContext = new AudioContextCtor();
      if (audioContext && typeof audioContext.resume === "function" && audioContext.state === "suspended") {
        audioContext.resume().catch(function () {});
      }
      var analyser = audioContext.createAnalyser();
      analyser.fftSize = 128;
      analyser.smoothingTimeConstant = 0.72;
      var source = audioContext.createMediaStreamSource(recorder.stream);
      source.connect(analyser);
      recorder.audioContext = audioContext;
      recorder.analyser = analyser;
      recorder.sourceNode = source;
      recorder.analyserData = new Uint8Array(analyser.fftSize);
      var tick = function () {
        if (state.voiceRecorder !== recorder || !recorder.analyser || !recorder.analyserData) {
          return;
        }
        recorder.analyser.getByteTimeDomainData(recorder.analyserData);
        var sum = 0;
        for (var i = 0; i < recorder.analyserData.length; i += 1) {
          var centered = (recorder.analyserData[i] - 128) / 128;
          sum += centered * centered;
        }
        var rms = Math.sqrt(sum / Math.max(1, recorder.analyserData.length));
        var nextHeight = clamp(0.2 + (rms * 3.4), 0.18, 1);
        recorder.waveSamples = (Array.isArray(recorder.waveSamples) ? recorder.waveSamples : []).slice(-VOICE_WAVE_BAR_COUNT + 1);
        recorder.waveSamples.push(nextHeight);
        renderComposerVoiceWave(recorder.waveSamples);
        recorder.waveRaf = window.requestAnimationFrame(tick);
      };
      recorder.waveRaf = window.requestAnimationFrame(tick);
    } catch (_error) {
      renderComposerVoiceWave(recorder.waveSamples);
    }
  }

  function voiceRecorderHint(recorder) {
    if (!recorder) return "برای قفل به بالا بکش، برای لغو به چپ بکش.";
    if (recorder.cancelReady) return "رها کن تا ضبط لغو شود.";
    if (recorder.lockReady) return "رها کن تا ضبط قفل شود.";
    if (recorder.locked && recorder.recording) return "ضبط قفل شد. برای پایان از توقف یا ارسال استفاده کن.";
    if (recorder.recording && recorder.gestureMode) return "برای قفل به بالا بکش، برای لغو به چپ بکش.";
    if (recorder.recording) return "برای پایان از توقف یا ارسال استفاده کن.";
    if (recorder.blob) return "پیام صوتی آماده ارسال است.";
    return "برای قفل به بالا بکش، برای لغو به چپ بکش.";
  }

  function updateVoiceUi() {
    var recorder = state.voiceRecorder;
    if (!composerVoice || !voiceBtn) return;
    var active = !!recorder;
    composerVoice.hidden = !active;
    composerVoice.classList.toggle("is-locked", !!(recorder && recorder.locked));
    composerVoice.classList.toggle("is-cancel-ready", !!(recorder && recorder.cancelReady));
    composerVoice.classList.toggle("is-lock-ready", !!(recorder && recorder.lockReady));
    voiceBtn.classList.toggle("is-recording", !!(recorder && recorder.recording));
    if (composerVoiceTimer) {
      var elapsed = recorder ? Math.max(0, Math.floor(toNumber(recorder.elapsedSeconds, 0))) : 0;
      composerVoiceTimer.textContent = formatDuration(elapsed);
    }
    if (composerVoiceHint) {
      composerVoiceHint.textContent = voiceRecorderHint(recorder);
    }
    if (composerVoiceLockChip) {
      composerVoiceLockChip.hidden = !(recorder && recorder.locked);
    }
    if (!recorder) {
      renderComposerVoiceWave([]);
    }
    if (voiceStopBtn) {
      voiceStopBtn.disabled = !recorder || !recorder.recording;
    }
    if (voiceSendBtn) {
      voiceSendBtn.disabled = !recorder || (!recorder.recording && !recorder.blob);
    }
    if (voiceCancelBtn) {
      voiceCancelBtn.disabled = !recorder;
    }
    updateComposerState();
  }

  function stopVoiceTracks(stream) {
    if (!stream || typeof stream.getTracks !== "function") return;
    stream.getTracks().forEach(function (track) {
      if (track && typeof track.stop === "function") {
        track.stop();
      }
    });
  }

  function resetVoiceRecorder() {
    var recorder = state.voiceRecorder;
    if (recorder && recorder.timerId) {
      window.clearInterval(recorder.timerId);
    }
    if (recorder) {
      recorder.disposed = true;
      recorder.sendAfterStop = false;
      recorder.recording = false;
      if (recorder.mediaRecorder) {
        recorder.mediaRecorder.ondataavailable = null;
        recorder.mediaRecorder.onstop = null;
        recorder.mediaRecorder.onerror = null;
        try {
          if (recorder.mediaRecorder.state && recorder.mediaRecorder.state !== "inactive") {
            recorder.mediaRecorder.stop();
          }
        } catch (_error) {}
      }
    }
    stopVoiceMonitoring(recorder);
    if (recorder && recorder.stream) {
      stopVoiceTracks(recorder.stream);
    }
    state.voiceGesture = null;
    state.voiceRecorder = null;
    updateVoiceUi();
  }

  function updateVoiceGesturePosition(clientX, clientY) {
    var recorder = state.voiceRecorder;
    var gesture = state.voiceGesture;
    if (!recorder || !gesture || recorder.locked || !recorder.recording) return;
    var deltaX = clientX - gesture.startX;
    var deltaY = clientY - gesture.startY;
    recorder.cancelReady = deltaX <= -VOICE_GESTURE_CANCEL_PX && Math.abs(deltaX) >= Math.abs(deltaY) * 0.9;
    recorder.lockReady = deltaY <= -VOICE_GESTURE_LOCK_PX && Math.abs(deltaY) >= Math.abs(deltaX) * 0.72;
    updateVoiceUi();
  }

  function releaseVoiceGesture(mode) {
    var recorder = state.voiceRecorder;
    state.voiceGesture = null;
    if (!recorder) return;
    if (mode === "cancel" || recorder.cancelReady) {
      resetVoiceRecorder();
      setComposerStatus("", "");
      return;
    }
    if (recorder.lockReady) {
      recorder.locked = true;
      recorder.gestureMode = false;
      recorder.lockReady = false;
      recorder.cancelReady = false;
      setComposerStatus("ضبط صدا قفل شد و ادامه دارد.", "");
      updateVoiceUi();
      return;
    }
    stopVoiceRecording(true);
  }

  async function startVoiceRecording(options) {
    var opts = asObject(options) || {};
    var conversation = activeConversation();
    if (!conversation) {
      showToast("ابتدا یک گفت‌وگو را انتخاب کن.");
      return;
    }
    if (conversation.permissions && conversation.permissions.canSend === false) {
      showToast("در این گفت‌وگو اجازه ارسال پیام ندارید.");
      return;
    }
    if (conversation.settings && conversation.settings.muted) {
      showToast("ارسال پیام در این گفت‌وگو بسته است.");
      return;
    }
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== "function" || typeof window.MediaRecorder !== "function") {
      showToast("مرورگر شما از ضبط صدا پشتیبانی نمی‌کند.");
      return;
    }
    if (state.voiceRecorder) {
      return;
    }

    try {
      var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      var mimeType = bestVoiceMimeType();
      var mediaRecorder = mimeType ? new window.MediaRecorder(stream, { mimeType: mimeType }) : new window.MediaRecorder(stream);
      var recorderState = {
        mediaRecorder: mediaRecorder,
        stream: stream,
        chunks: [],
        startedAt: Date.now(),
        elapsedSeconds: 0,
        timerId: null,
        recording: true,
        blob: null,
        mimeType: mimeType || mediaRecorder.mimeType || "audio/webm",
        sendAfterStop: false,
        gestureMode: !!opts.gestureMode,
        locked: false,
        lockReady: false,
        cancelReady: false,
        audioContext: null,
        analyser: null,
        analyserData: null,
        sourceNode: null,
        waveSamples: [],
        waveRaf: 0,
        disposed: false
      };
      state.voiceRecorder = recorderState;
      if (voiceBtn) {
        voiceBtn.classList.add("is-recording");
      }

      mediaRecorder.ondataavailable = function (event) {
        if (recorderState.disposed) return;
        if (!event || !event.data || !event.data.size) return;
        recorderState.chunks.push(event.data);
      };
      mediaRecorder.onstop = function () {
        if (recorderState.disposed) {
          stopVoiceTracks(recorderState.stream);
          return;
        }
        recorderState.recording = false;
        recorderState.elapsedSeconds = Math.max(1, Math.floor((Date.now() - recorderState.startedAt) / 1000));
        if (recorderState.timerId) {
          window.clearInterval(recorderState.timerId);
          recorderState.timerId = null;
        }
        stopVoiceMonitoring(recorderState);
        if (recorderState.chunks.length) {
          recorderState.blob = new Blob(recorderState.chunks, { type: recorderState.mimeType || "audio/webm" });
        }
        stopVoiceTracks(recorderState.stream);
        updateVoiceUi();
        var hasUsableBlob = !!(recorderState.blob && recorderState.elapsedSeconds >= 1 && recorderState.blob.size >= 1200);
        if (!hasUsableBlob) {
          showToast("پیام صوتی خیلی کوتاه بود.");
          setComposerStatus("", "");
          resetVoiceRecorder();
          return;
        }
        if (!recorderState.sendAfterStop) {
          setComposerStatus("پیام صوتی آماده ارسال است.", "");
          return;
        }
        if (recorderState.sendAfterStop && recorderState.blob) {
          if (recorderState.elapsedSeconds < 1 || recorderState.blob.size < 1200) {
            showToast("پیام صوتی خیلی کوتاه بود.");
            resetVoiceRecorder();
            return;
          }
          sendRecordedVoice(recorderState).catch(function (error) {
            setComposerStatus("پیام صوتی آماده ارسال است.", "");
            showToast(error && error.message ? error.message : "ارسال پیام صوتی انجام نشد.");
          });
        }
      };
      mediaRecorder.onerror = function () {
        if (recorderState.disposed) return;
        showToast("ضبط صدا با خطا متوقف شد.");
        resetVoiceRecorder();
      };

      mediaRecorder.start(250);
      startVoiceMonitoring(recorderState);
      recorderState.timerId = window.setInterval(function () {
        recorderState.elapsedSeconds = Math.max(0, Math.floor((Date.now() - recorderState.startedAt) / 1000));
        updateVoiceUi();
      }, 300);
      setComposerStatus("در حال ضبط پیام صوتی...", "");
      updateVoiceUi();
      if (state.voiceGesture && opts.gestureMode) {
        if (state.voiceGesture.releasedBeforeReady) {
          releaseVoiceGesture(state.voiceGesture.releaseMode || "");
        } else {
          updateVoiceGesturePosition(state.voiceGesture.lastX, state.voiceGesture.lastY);
        }
      }
    } catch (_error) {
      showToast("دسترسی میکروفون داده نشد یا ضبط صدا شروع نشد.");
      resetVoiceRecorder();
    }
  }

  function stopVoiceRecording(sendAfterStop) {
    var recorder = state.voiceRecorder;
    if (!recorder) return;
    if (!recorder.recording) {
      if (sendAfterStop && recorder.blob) {
        recorder.sendAfterStop = true;
        sendRecordedVoice(recorder).catch(function (error) {
          showToast(error && error.message ? error.message : "ارسال پیام صوتی انجام نشد.");
        });
      }
      return;
    }
    recorder.sendAfterStop = !!sendAfterStop;
    try {
      recorder.mediaRecorder.stop();
    } catch (_error) {
      resetVoiceRecorder();
    }
    updateVoiceUi();
  }

  async function sendRecordedVoice(recorderState) {
    if (!recorderState || !recorderState.blob) {
      throw new Error("فایل صوتی آماده ارسال نیست.");
    }
    if (Math.max(0, Math.floor(toNumber(recorderState.elapsedSeconds, 0))) < 1 || recorderState.blob.size < 1200) {
      throw new Error("پیام صوتی خیلی کوتاه بود.");
    }
    var extension = voiceFileExtension(recorderState.mimeType);
    var fileName = "voice-" + Date.now() + "." + extension;
    var file = new File([recorderState.blob], fileName, {
      type: recorderState.mimeType || recorderState.blob.type || "audio/webm"
    });
    var attachment = await queueAttachmentUpload(file, {
      isVoice: true,
      durationSeconds: recorderState.elapsedSeconds || 0
    });
    if (!attachment || !attachment.id) {
      throw new Error("بارگذاری پیام صوتی انجام نشد.");
    }
    await sendCurrentMessage({
      attachmentIds: [attachment.id],
      text: "",
      fromVoice: true
    });
    resetVoiceRecorder();
  }

  async function sendCurrentMessage(options) {
    var opts = asObject(options) || {};
    var conversation = activeConversation();
    if (!conversation || !chatTextEl || !sendBtn || sendBtn.disabled) return false;
    var response = null;

    if (composerHasUploadingItems()) {
      showToast("بارگذاری فایل‌ها هنوز کامل نشده است.");
      return;
    }

    var text = opts.text != null ? toText(opts.text).trim() : toText(chatTextEl.value).trim();
    if (text.length > MAX_MESSAGE_SIZE) {
      text = text.slice(0, MAX_MESSAGE_SIZE);
    }

    var attachmentIds = Array.isArray(opts.attachmentIds)
      ? opts.attachmentIds.map(function (value) { return normalizeSpace(value); }).filter(Boolean)
      : uploadedComposerItems().map(function (item) { return normalizeSpace(item.attachment.id); }).filter(Boolean);
    var messageMeta = normalizeMessageMeta(opts.meta);

    if (!text && !attachmentIds.length && !messageMeta) {
      return false;
    }

    var payload = {
      conversationId: conversation.id,
      text: text
    };
    if (attachmentIds.length) {
      payload.attachmentIds = JSON.stringify(attachmentIds);
      payload.kind = attachmentIds.length === 1 && opts.fromVoice ? "voice" : "attachment";
    }
    if (messageMeta) {
      payload.meta = JSON.stringify(messageMeta);
    }
    if (state.replyTargetId) {
      payload.replyTo = String(state.replyTargetId);
    }

    sendBtn.disabled = true;
    setComposerStatus("در حال ارسال...", "");

    try {
      response = await apiPost("send", payload);
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ارسال پیام انجام نشد.");
      state.connectionIssue = false;

      var message = normalizeMessage(response.message);
      if (message && message.conversationId === state.activeConversationId) {
        appendMessages([message], {
          replaceAll: false,
          forceStick: true,
          smooth: true,
          markNew: true
        });
      }

      var nextConversation = normalizeConversation(response.conversation);
      if (nextConversation) {
        upsertConversation(nextConversation);
        rebuildConversationsFromMap();
        renderConversationList();
        updateThreadHead();
      }

      if (opts.text == null) {
        chatTextEl.value = "";
        clearComposerDraft(conversation.id);
      }
      autosizeComposer();
      closeMentionSuggestions();
      clearReplyTarget();
      clearTypingActivity(false);
      clearComposerAttachments(attachmentIds);
      setComposerStatus("", "");
      setConnectionState("live", "متصل");
      return true;
    } catch (error) {
      if (response && (response.networkError || toNumber(response.httpStatus, 0) <= 0)) {
        state.connectionIssue = true;
        setConnectionState("issue", "در انتظار اتصال");
        if (!attachmentIds.length) {
          var queuedEntry = queueOfflineChatSend(payload);
          if (queuedEntry) {
            if (opts.text == null) {
              chatTextEl.value = "";
              clearComposerDraft(conversation.id);
            }
            autosizeComposer();
            closeMentionSuggestions();
            clearReplyTarget();
            clearTypingActivity(false);
            setComposerStatus("پیام در صف آفلاین ذخیره شد و بعد از آنلاین شدن ارسال می‌شود.", "");
            showToast("پیام در صف آفلاین ذخیره شد.");
            return true;
          }
        }
      }
      setComposerStatus(error && error.message ? error.message : "ارسال پیام انجام نشد.", "error");
      showToast(error && error.message ? error.message : "ارسال پیام انجام نشد.");
    } finally {
      if (!chatTextEl.disabled) {
        sendBtn.disabled = false;
      }
      updateComposerState();
    }
  }

  async function toggleReaction(message, emoji, options) {
    var opts = asObject(options) || {};
    var conversation = activeConversation();
    var normalizedEmoji = normalizeSpace(emoji);
    if (!conversation || !message || !normalizedEmoji) return false;
    if (!isLikelyEmoji(normalizedEmoji)) {
      if (!opts.silentFailure) {
        showToast("ایموجی واکنش معتبر نیست.");
      }
      return false;
    }
    if (!reactionAllowedInActiveConversation(normalizedEmoji)) {
      if (!opts.silentFailure) {
        showToast("این واکنش در تنظیمات گفتگو مجاز نیست.");
      }
      return false;
    }

    try {
      var response = await apiPost("react", {
        conversationId: conversation.id,
        id: String(message.id),
        messageId: String(message.id),
        emoji: normalizedEmoji
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ثبت واکنش انجام نشد.");

      var updated = normalizeMessage(response.message);
      if (updated) {
        replaceMessageInDom(updated);
        var stillOwn = reactionEntries(updated).some(function (entry) {
          return entry.emoji === normalizedEmoji && entry.own;
        });
        if (stillOwn) {
          pushRecentReaction(normalizedEmoji);
        }
        if (typeof opts.afterUpdate === "function") {
          opts.afterUpdate(updated, {
            emoji: normalizedEmoji,
            own: stillOwn
          });
        }
      }
      setConnectionState("live", "متصل");
      if (opts.closeContext !== false) {
        closeContextMenu();
      }
      return true;
    } catch (error) {
      if (!opts.silentFailure) {
        showToast(error && error.message ? error.message : "ثبت واکنش انجام نشد.");
      }
      return false;
    }
  }

  function triggerGestureHeartReaction(message) {
    if (!message) return Promise.resolve(false);
    return toggleReaction(message, TELEGRAM_HEART_REACTION, {
      silentFailure: true,
      closeContext: false,
      afterUpdate: function (updated, meta) {
        if (meta && meta.own) {
          playReactionBurst(updated.id, meta.emoji);
        }
      }
    });
  }

  async function submitEditedMessage(message, nextText) {
    var conversation = activeConversation();
    if (!conversation || !message) return false;
    var next = toText(nextText).trim();
    if (!next) {
      showToast("متن پیام نمی‌تواند خالی باشد.");
      return false;
    }

    try {
      var response = await apiPost("edit", {
        conversationId: conversation.id,
        id: String(message.id),
        text: next
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ویرایش پیام انجام نشد.");

      var updated = normalizeMessage(response.message);
      if (updated) {
        replaceMessageInDom(updated);
      }
      showToast("پیام ویرایش شد.");
      return true;
    } catch (error) {
      showToast(error && error.message ? error.message : "ویرایش پیام انجام نشد.");
      return false;
    }
  }

  async function saveEditedMessageFromModal() {
    var message = findMessage(state.pendingEditMessageId);
    if (!message || !editTextInput) return;
    var next = toText(editTextInput.value).trim();
    if (!next) {
      showToast("متن پیام نمی‌تواند خالی باشد.");
      return;
    }
    setModalBusy("edit", true);
    var ok = await submitEditedMessage(message, next);
    setModalBusy("edit", false);
    if (ok) {
      closeModal(true);
    }
  }

  function openEditPrompt(message) {
    if (!message) return;
    if (!editModal || !editTextInput) {
      var edited = window.prompt("ویرایش پیام", toText(message.text));
      if (edited === null) return;
      submitEditedMessage(message, edited);
      return;
    }
    state.pendingEditMessageId = message.id;
    editTextInput.value = toText(message.text);
    openModal(editModal, "edit");
    window.setTimeout(function () {
      if (!editTextInput) return;
      editTextInput.focus({ preventScroll: true });
      editTextInput.setSelectionRange(editTextInput.value.length, editTextInput.value.length);
    }, 40);
  }

  async function deleteMessage(message) {
    var conversation = activeConversation();
    if (!conversation || !message) return;
    var approved = await openConfirmDialog({
      title: "حذف پیام",
      message: "این پیام برای همیشه از گفتگو حذف می‌شود.",
      acceptLabel: "حذف پیام",
      danger: true
    });
    if (!approved) return;

    try {
      var response = await apiPost("delete", {
        conversationId: conversation.id,
        id: String(message.id)
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "حذف پیام انجام نشد.");

      removeMessageFromDom(response.deletedId || message.id);
      showToast("پیام حذف شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "حذف پیام انجام نشد.");
    }
  }

  async function togglePin(message, pin) {
    var conversation = activeConversation();
    if (!conversation || !message) return;

    try {
      var response = await apiPost("pin", {
        conversationId: conversation.id,
        id: String(message.id),
        pinned: pin ? "1" : "0"
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "تغییر سنجاق پیام انجام نشد.");

      var updated = normalizeMessage(response.message);
      if (updated) {
        replaceMessageInDom(updated);
      }

      await syncConversation({
        forceFull: false,
        includeMembers: state.infoSheetOpen,
        silent: true,
        conversationId: conversation.id
      });

      showToast(pin ? "پیام سنجاق شد." : "سنجاق پیام برداشته شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "تغییر سنجاق پیام انجام نشد.");
    }
  }

  async function setConversationPinById(conversationId, pinned, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    try {
      var response = await apiPost("setPinConversation", {
        conversationId: conversation.id,
        pinned: pinned ? "1" : "0"
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "تغییر وضعیت سنجاق انجام نشد.");
      await refreshAfterConversationAction(response, state.activeConversationId || conversation.id, {
        keepCurrentActive: true,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: false
      });
      if (!opts.silentToast) {
        showToast(pinned ? "گفتگو سنجاق شد." : "سنجاق گفتگو برداشته شد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "تغییر وضعیت سنجاق انجام نشد.");
      }
      return false;
    }
  }

  async function setConversationReadStateById(conversationId, read, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    try {
      var action = read ? "markRead" : "markUnread";
      var payload = { conversationId: conversation.id };
      if (read && conversation.lastMessage && conversation.lastMessage.id) {
        payload.lastReadMessageId = String(conversation.lastMessage.id);
      }
      var response = await apiPost(action, payload);
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "به‌روزرسانی وضعیت خوانده‌نشده انجام نشد.");
      await refreshAfterConversationAction(response, state.activeConversationId || conversation.id, {
        keepCurrentActive: true,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: false
      });
      if (!opts.silentToast) {
        showToast(read ? "گفتگو خوانده‌شده علامت خورد." : "گفتگو خوانده‌نشده علامت خورد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "به‌روزرسانی وضعیت خوانده‌نشده انجام نشد.");
      }
      return false;
    }
  }

  async function setConversationMuteById(conversationId, muted, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    try {
      var response = await apiPost("setMute", {
        conversationId: conversation.id,
        muted: muted ? "1" : "0"
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "تغییر وضعیت سکوت گفتگو انجام نشد.");
      await refreshAfterConversationAction(response, state.activeConversationId || conversation.id, {
        keepCurrentActive: true,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: false
      });
      if (!opts.silentToast) {
        showToast(muted ? "گفتگو بی‌صدا شد." : "گفتگو از حالت بی‌صدا خارج شد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "تغییر وضعیت سکوت گفتگو انجام نشد.");
      }
      return false;
    }
  }

  async function setConversationArchiveById(conversationId, archived, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    try {
      var response = await apiPost("setArchive", {
        conversationId: conversation.id,
        archived: archived ? "1" : "0"
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "تغییر وضعیت بایگانی انجام نشد.");
      await refreshAfterConversationAction(response, state.activeConversationId || conversation.id, {
        keepCurrentActive: true,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: false
      });
      if (!opts.silentToast) {
        showToast(archived ? "گفتگو بایگانی شد." : "گفتگو از بایگانی خارج شد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "تغییر وضعیت بایگانی انجام نشد.");
      }
      return false;
    }
  }

  async function deleteConversationById(conversationId, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    if (!opts.skipConfirm) {
      var isDirectConversation = conversation.type === "direct";
      var approved = await openConfirmDialog({
        title: "حذف گفتگو",
        message: isDirectConversation
          ? "این گفتگوی خصوصی از لیست شما حذف می‌شود."
          : "این گفتگوی گروهی برای همه اعضا حذف می‌شود.",
        acceptLabel: "حذف",
        danger: true
      });
      if (!approved) return false;
    }
    try {
      var response = await apiPost("deleteConversation", {
        conversationId: conversation.id
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "حذف گفتگو انجام نشد.");
      state.selectedConversationIds.delete(conversation.id);
      await refreshAfterConversationAction(response, "", {
        keepCurrentActive: !opts.focusDeleted,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: true
      });
      if (!opts.silentToast) {
        showToast("گفتگو حذف شد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "حذف گفتگو انجام نشد.");
      }
      return false;
    }
  }

  async function runConversationBatchAction(actionKey) {
    var action = normalizeSpace(actionKey).toLowerCase();
    var list = selectedConversations();
    if (!list.length) {
      showToast("هیچ گفتگویی انتخاب نشده است.");
      return;
    }

    var requiresDeleteConfirm = action === "delete";
    if (requiresDeleteConfirm) {
      var approved = await openConfirmDialog({
        title: "حذف گفتگوهای انتخاب‌شده",
        message: "گفتگوهای انتخاب‌شده در موارد مجاز حذف می‌شوند.",
        acceptLabel: "حذف موارد انتخاب‌شده",
        danger: true
      });
      if (!approved) return;
    }

    closeListContextMenu();
    var success = 0;
    var skipped = 0;
    for (var i = 0; i < list.length; i += 1) {
      var conversation = list[i];
      if (!conversation) {
        skipped += 1;
        continue;
      }
      var permissions = conversation.permissions || {};
      var ok = false;
      if (action === "read") {
        if (!permissions.canMarkRead) {
          skipped += 1;
          continue;
        }
        ok = await setConversationReadStateById(conversation.id, true, { skipSync: true, silentToast: true });
      } else if (action === "unread") {
        if (!permissions.canMarkUnread) {
          skipped += 1;
          continue;
        }
        ok = await setConversationReadStateById(conversation.id, false, { skipSync: true, silentToast: true });
      } else if (action === "pin") {
        if (!permissions.canPinConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationPinById(conversation.id, true, { skipSync: true, silentToast: true });
      } else if (action === "unpin") {
        if (!permissions.canPinConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationPinById(conversation.id, false, { skipSync: true, silentToast: true });
      } else if (action === "archive") {
        if (!permissions.canArchiveConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationArchiveById(conversation.id, true, { skipSync: true, silentToast: true });
      } else if (action === "unarchive") {
        if (!permissions.canArchiveConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationArchiveById(conversation.id, false, { skipSync: true, silentToast: true });
      } else if (action === "mute") {
        if (!permissions.canMuteConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationMuteById(conversation.id, true, { skipSync: true, silentToast: true });
      } else if (action === "unmute") {
        if (!permissions.canMuteConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationMuteById(conversation.id, false, { skipSync: true, silentToast: true });
      } else if (action === "delete") {
        if (!permissions.canDeleteConversation) {
          skipped += 1;
          continue;
        }
        ok = await deleteConversationById(conversation.id, { skipConfirm: true, skipSync: true, silentToast: true });
      }
      if (ok) {
        success += 1;
      } else {
        skipped += 1;
      }
    }

    exitConversationSelectionMode();
    await syncConversation({
      forceFull: true,
      includeMembers: state.infoSheetOpen,
      silent: true,
      conversationId: state.activeConversationId || ""
    });

    showToast(success.toLocaleString("fa-IR") + " انجام شد" + (skipped > 0 ? (" • " + skipped.toLocaleString("fa-IR") + " مورد رد شد") : ""));
  }

  async function setConversationMute(muted) {
    var conversation = activeConversation();
    if (!conversation) return;
    await setConversationMuteById(conversation.id, muted);
  }

  async function refreshAfterConversationAction(response, fallbackConversationId, options) {
    var opts = asObject(options) || {};
    var conversations = (Array.isArray(response && response.conversations) ? response.conversations : [])
      .map(normalizeConversation)
      .filter(Boolean);
    if (conversations.length) {
      replaceConversations(conversations);
    } else {
      rebuildConversationsFromMap();
    }

    var responseConversation = normalizeConversation(response && response.conversation);
    if (responseConversation) {
      upsertConversation(responseConversation);
      rebuildConversationsFromMap();
    }

    var preferredConversationId = normalizeSpace(opts.keepCurrentActive ? state.activeConversationId : "");
    if (!preferredConversationId || !state.conversationsById.has(preferredConversationId)) {
      preferredConversationId = normalizeSpace(
        (response && response.conversationId)
        || (responseConversation && responseConversation.id)
        || fallbackConversationId
        || state.activeConversationId
        || (state.conversations[0] && state.conversations[0].id)
        || ""
      );
    }
    state.activeConversationId = preferredConversationId;

    if (opts.skipSync) {
      var hasActiveConversation = !!state.activeConversationId && state.conversationsById.has(state.activeConversationId);
      setThreadVisible(hasActiveConversation);
      if (!hasActiveConversation) {
        clearThreadState();
      }
      renderConversationList();
      updateThreadHead();
      updateComposerState();
      updatePinnedUi();
      var activeMutedConversation = activeConversation();
      updateMuteUi(activeMutedConversation && activeMutedConversation.settings ? activeMutedConversation.settings : { muted: false });
      updateInfoSheet();
      updateSelectionUi();
      return;
    }

    state.lastMessageId = 0;
    await syncConversation({
      forceFull: opts.forceFull !== false,
      includeMembers: state.infoSheetOpen,
      silent: !!opts.silent,
      conversationId: preferredConversationId
    });
  }

  async function setConversationArchive(archived) {
    var conversation = activeConversation();
    if (!conversation) return;
    await setConversationArchiveById(conversation.id, archived);
  }

  async function clearConversationHistory() {
    var conversation = activeConversation();
    if (!conversation) return;
    var approved = await openConfirmDialog({
      title: "پاک‌کردن تاریخچه",
      message: "تمام پیام‌های این گفتگو پاک می‌شود. این عملیات قابل بازگشت نیست.",
      acceptLabel: "پاک‌کردن تاریخچه",
      danger: true
    });
    if (!approved) return;

    try {
      var response = await apiPost("clearHistory", {
        conversationId: conversation.id
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "پاک‌کردن تاریخچه انجام نشد.");

      await refreshAfterConversationAction(response, conversation.id);
      var clearedCount = Math.max(0, Math.floor(toNumber(response && response.clearedCount, 0)));
      if (clearedCount > 0) {
        showToast("تاریخچه گفتگو پاک شد (" + clearedCount.toLocaleString("fa-IR") + " پیام).");
      } else {
        showToast("تاریخچه از قبل خالی بود.");
      }
    } catch (error) {
      showToast(error && error.message ? error.message : "پاک‌کردن تاریخچه انجام نشد.");
    }
  }

  async function leaveCurrentConversation() {
    var conversation = activeConversation();
    if (!conversation) return;
    var approved = await openConfirmDialog({
      title: "ترک گروه",
      message: "با ترک گروه، پیام‌های جدید این گروه را دریافت نمی‌کنی.",
      acceptLabel: "ترک گروه",
      danger: true
    });
    if (!approved) return;

    try {
      var response = await apiPost("leaveConversation", {
        conversationId: conversation.id
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "خروج از گروه انجام نشد.");

      closeInfoSheet();
      await refreshAfterConversationAction(response, "");
      showToast("از گروه خارج شدی.");
    } catch (error) {
      showToast(error && error.message ? error.message : "خروج از گروه انجام نشد.");
    }
  }

  async function deleteCurrentConversation() {
    var conversation = activeConversation();
    if (!conversation) return;
    var deleted = await deleteConversationById(conversation.id);
    if (deleted) {
      closeInfoSheet();
    }
  }

  async function startDirectConversation(studentNumber) {
    var peerStudentNumber = normalizeStudentNumber(studentNumber);
    if (!peerStudentNumber || state.pendingDirectStart) return;

    state.pendingDirectStart = true;
    setModalBusy("dm", true);

    try {
      var response = await apiPost("startDirect", {
        peerStudentNumber: peerStudentNumber
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "شروع گفت‌وگوی خصوصی انجام نشد.");

      var conversations = (Array.isArray(response.conversations) ? response.conversations : [])
        .map(normalizeConversation)
        .filter(Boolean);
      if (conversations.length) {
        replaceConversations(conversations);
      }

      var currentConversation = normalizeConversation(response.conversation);
      if (currentConversation) {
        upsertConversation(currentConversation);
        rebuildConversationsFromMap();
      }

      var conversationId = normalizeSpace(
        response.conversationId || (currentConversation && currentConversation.id)
      );
      if (!conversationId) {
        throw new Error("گفت‌وگوی خصوصی ایجاد شد اما شناسه معتبر برنگشت.");
      }

      closeModal(true);
      await openConversation(conversationId, {
        forceFull: true,
        source: "dm-create",
        silent: false,
        mobileView: "list"
      });
      showToast("گفت‌وگوی خصوصی آماده است.");
    } catch (error) {
      showToast(error && error.message ? error.message : "شروع گفت‌وگوی خصوصی انجام نشد.");
    } finally {
      state.pendingDirectStart = false;
      setModalBusy("dm", false);
    }
  }

  async function openSavedMessagesConversation() {
    if (state.pendingDirectStart) return;
    state.pendingDirectStart = true;
    setModalBusy("dm", true);
    try {
      var response = await apiPost("openSavedMessages", {});
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "باز کردن پیام‌های ذخیره‌شده انجام نشد.");

      var conversations = (Array.isArray(response.conversations) ? response.conversations : [])
        .map(normalizeConversation)
        .filter(Boolean);
      if (conversations.length) {
        replaceConversations(conversations);
      }

      var currentConversation = normalizeConversation(response.conversation);
      if (currentConversation) {
        upsertConversation(currentConversation);
        rebuildConversationsFromMap();
      }

      var conversationId = normalizeSpace(
        response.conversationId || (currentConversation && currentConversation.id)
      );
      if (!conversationId) {
        throw new Error("شناسه معتبری برای پیام‌های ذخیره‌شده برنگشت.");
      }

      closeModal(true);
      await openConversation(conversationId, {
        forceFull: true,
        source: "saved-open",
        silent: false,
        mobileView: "list"
      });
      showToast("پیام‌های ذخیره‌شده آماده است.");
    } catch (error) {
      showToast(error && error.message ? error.message : "باز کردن پیام‌های ذخیره‌شده انجام نشد.");
    } finally {
      state.pendingDirectStart = false;
      setModalBusy("dm", false);
    }
  }

  async function createGroupConversation() {
    if (state.pendingGroupCreate) return;

    var selected = Array.from(state.groupMemberSelection);
    var conversationKind = groupKindChannelInput && groupKindChannelInput.checked ? "channel" : "group";
    if (conversationKind === "group" && selected.length < 2) {
      showToast("برای گروه حداقل دو عضو دیگر انتخاب کن. برای گفتگوی یک‌به‌یک از پیام خصوصی استفاده کن.");
      setGroupCreateStep("members");
      return;
    }

    var title = normalizeSpace(groupTitleInput && groupTitleInput.value);
    if (!title) {
      showToast("نام گفتگو را وارد کن.");
      setGroupCreateStep("details");
      if (groupTitleInput) groupTitleInput.focus({ preventScroll: true });
      return;
    }

    var about = normalizeSpace(groupAboutInput && groupAboutInput.value);

    state.pendingGroupCreate = true;
    setModalBusy("group", true);

    try {
      var response = await apiPost("createGroup", {
        title: title,
        about: about,
        membersJson: JSON.stringify(selected),
        kind: conversationKind
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ساخت گفتگو انجام نشد.");

      var conversations = (Array.isArray(response.conversations) ? response.conversations : [])
        .map(normalizeConversation)
        .filter(Boolean);
      if (conversations.length) {
        replaceConversations(conversations);
      }

      var currentConversation = normalizeConversation(response.conversation);
      if (currentConversation) {
        upsertConversation(currentConversation);
        rebuildConversationsFromMap();
      }

      var conversationId = normalizeSpace(
        response.conversationId || (currentConversation && currentConversation.id)
      );
      if (!conversationId) {
        throw new Error("گروه ساخته شد اما شناسه معتبر برنگشت.");
      }

      closeModal(true);
      await openConversation(conversationId, {
        forceFull: true,
        source: "group-create",
        silent: false,
        mobileView: "list"
      });
      var createdGroupTitle = normalizeSpace(currentConversation && currentConversation.title) || title || "گروه";
      showToast("گروه «" + createdGroupTitle + "» ساخته شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "ساخت گفتگو انجام نشد.");
    } finally {
      state.pendingGroupCreate = false;
      setModalBusy("group", false);
    }
  }

  function openDmCreationFlow() {
    return loadDirectory(false).then(function () {
      openModal(dmModal, "dm");
      if (dmSearch) dmSearch.value = "";
      renderDmList();
      if (dmSearch && !isMobileViewport()) {
        dmSearch.focus({ preventScroll: true });
      }
    }).catch(function (error) {
      showToast(error && error.message ? error.message : "بارگذاری فهرست کاربران انجام نشد.");
    });
  }

  function openGroupCreationFlow() {
    return loadDirectory(false).then(function () {
      openModal(groupModal, "group");
      resetGroupCreateFlow();
      renderGroupMembersPicker();
      if (groupSearch && !isMobileViewport()) {
        groupSearch.focus({ preventScroll: true });
      }
    }).catch(function (error) {
      showToast(error && error.message ? error.message : "بارگذاری فهرست کاربران انجام نشد.");
    });
  }

  function bindEvents() {
    if (conversationSearch) {
      conversationSearch.addEventListener("input", function () {
        state.conversationFilter = conversationSearch.value || "";
        renderConversationList();
      });
    }
    if (conversationFilterTabs) {
      conversationFilterTabs.addEventListener("click", function (event) {
        var button = event.target && event.target.closest ? event.target.closest("[data-list-filter]") : null;
        if (!button) return;
        setConversationListCategory(button.getAttribute("data-list-filter"));
      });

      // Enable horizontal swipe on mobile to switch conversation filter tabs
      (function () {
        var swipeStartX = 0;
        var swipeStartY = 0;
        var swipeActive = false;
        var swipeTracking = false;
        var swipeThreshold = 42;
        var swipeOrder = ["all", "direct", "groups", "unread"];
        var swipeTargets = [conversationFilterTabs, conversationList, conversationPane].filter(Boolean);

        function resetSwipeVisual(settling) {
          if (!conversationList) return;
          conversationList.classList.toggle("is-swipe-settling", !!settling);
          conversationList.classList.remove("is-swiping");
          conversationList.style.transform = "";
          if (settling) {
            window.setTimeout(function () {
              if (conversationList) conversationList.classList.remove("is-swipe-settling");
            }, 190);
          }
        }

        function onSwipeStart(e) {
          if (!isMobileViewport() || !e.touches || !e.touches.length) return;
          var target = e.target && e.target.closest ? e.target.closest("input, textarea, select") : null;
          if (target) return;
          swipeStartX = e.touches[0].clientX;
          swipeStartY = e.touches[0].clientY;
          swipeActive = true;
          swipeTracking = false;
          resetSwipeVisual(false);
        }

        function onSwipeMove(e) {
          if (!swipeActive) return;
          var dx = e.touches[0].clientX - swipeStartX;
          var dy = e.touches[0].clientY - swipeStartY;
          if (!swipeTracking && Math.abs(dx) > 12 && Math.abs(dx) > Math.abs(dy) * 1.25) {
            swipeTracking = true;
            if (conversationList) conversationList.classList.add("is-swiping");
          }
          if (swipeTracking) {
            if (e.cancelable) e.preventDefault();
            if (conversationList) {
              var damped = Math.max(-120, Math.min(120, dx * 0.52));
              conversationList.style.transform = "translate3d(" + damped.toFixed(1) + "px, 0, 0)";
            }
          } else if (Math.abs(dy) > 14) {
            swipeActive = false;
            resetSwipeVisual(false);
          }
        }

        function onSwipeEnd(e) {
          if (!swipeActive) {
            resetSwipeVisual(true);
            return;
          }
          var touch = e.changedTouches && e.changedTouches[0];
          var dx = touch ? (touch.clientX - swipeStartX) : 0;
          var dy = touch ? (touch.clientY - swipeStartY) : 0;
          if (swipeTracking && Math.abs(dx) > swipeThreshold && Math.abs(dx) > Math.abs(dy)) {
            var current = normalizeConversationListCategory(state.conversationListCategory) || "all";
            var idx = swipeOrder.indexOf(current);
            if (idx === -1) idx = 0;
            var nextIdx = dx < 0 ? Math.max(0, idx - 1) : Math.min(swipeOrder.length - 1, idx + 1);
            var next = swipeOrder[nextIdx];
            if (next !== current) setConversationListCategory(next);
          }
          swipeActive = false;
          swipeTracking = false;
          resetSwipeVisual(true);
        }

        swipeTargets.forEach(function (target) {
          target.addEventListener("touchstart", onSwipeStart, { passive: true });
          target.addEventListener("touchmove", onSwipeMove, { passive: false });
          target.addEventListener("touchend", onSwipeEnd, { passive: true });
          target.addEventListener("touchcancel", function () {
            swipeActive = false;
            swipeTracking = false;
            resetSwipeVisual(true);
          }, { passive: true });
        });
      })();
    }
    if (conversationSearchBtn && conversationSearchWrap) {
      conversationSearchBtn.addEventListener("click", function () {
        var isHidden = conversationSearchWrap.hidden;
        conversationSearchWrap.hidden = !isHidden;
        if (!isHidden && conversationSearch) {
          conversationSearch.value = "";
          state.conversationFilter = "";
          renderConversationList();
        }
        if (isHidden && conversationSearch) {
          conversationSearch.focus();
        }
      });
      if (conversationSearch) {
        conversationSearch.addEventListener("keydown", function (e) {
          if (e.key === "Escape") {
            conversationSearchWrap.hidden = true;
            conversationSearch.value = "";
            state.conversationFilter = "";
            renderConversationList();
          }
        });
      }
    }
    var composeBtn = $("compose-btn");
    var composeMenu = $("compose-menu");
    var composeMenuWrap = $("compose-menu-wrap");
    if (composeBtn && composeMenu) {
      composeBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        composeMenu.hidden = !composeMenu.hidden;
      });
      composeMenu.addEventListener("click", function () {
        composeMenu.hidden = true;
      });
      document.addEventListener("click", function (e) {
        if (composeMenuWrap && !composeMenuWrap.contains(e.target)) {
          composeMenu.hidden = true;
        }
      });
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") composeMenu.hidden = true;
      });
    }
    if (conversationManageBtn) {
      conversationManageBtn.addEventListener("click", function () {
        if (state.listSelectionMode) {
          exitConversationSelectionMode();
        } else {
          enterConversationSelectionMode("");
        }
      });
    }
    if (conversationSelectionClear) {
      conversationSelectionClear.addEventListener("click", function () {
        exitConversationSelectionMode();
      });
    }
    if (conversationSelectionToggleAll) {
      conversationSelectionToggleAll.addEventListener("click", function () {
        toggleSelectAllVisibleConversations();
      });
    }
    if (conversationBatchRead) {
      conversationBatchRead.addEventListener("click", function () { runConversationBatchAction("read"); });
    }
    if (conversationBatchUnread) {
      conversationBatchUnread.addEventListener("click", function () { runConversationBatchAction("unread"); });
    }
    if (conversationBatchPin) {
      conversationBatchPin.addEventListener("click", function () { runConversationBatchAction("pin"); });
    }
    if (conversationBatchUnpin) {
      conversationBatchUnpin.addEventListener("click", function () { runConversationBatchAction("unpin"); });
    }
    if (conversationBatchArchive) {
      conversationBatchArchive.addEventListener("click", function () { runConversationBatchAction("archive"); });
    }
    if (conversationBatchUnarchive) {
      conversationBatchUnarchive.addEventListener("click", function () { runConversationBatchAction("unarchive"); });
    }
    if (conversationBatchMute) {
      conversationBatchMute.addEventListener("click", function () { runConversationBatchAction("mute"); });
    }
    if (conversationBatchUnmute) {
      conversationBatchUnmute.addEventListener("click", function () { runConversationBatchAction("unmute"); });
    }
    if (conversationBatchDelete) {
      conversationBatchDelete.addEventListener("click", function () { runConversationBatchAction("delete"); });
    }
    if (messageSelectionClear) {
      messageSelectionClear.addEventListener("click", function () {
        exitMessageSelectionMode();
      });
    }
    if (messageSelectionToggleAll) {
      messageSelectionToggleAll.addEventListener("click", function () {
        toggleSelectAllVisibleMessages();
      });
    }
    if (messageBatchCopy) {
      messageBatchCopy.addEventListener("click", function () { runMessageBatchAction("copy"); });
    }
    if (messageBatchForward) {
      messageBatchForward.addEventListener("click", function () { runMessageBatchAction("forward"); });
    }
    if (messageBatchPin) {
      messageBatchPin.addEventListener("click", function () { runMessageBatchAction("pin"); });
    }
    if (messageBatchUnpin) {
      messageBatchUnpin.addEventListener("click", function () { runMessageBatchAction("unpin"); });
    }
    if (messageBatchDelete) {
      messageBatchDelete.addEventListener("click", function () { runMessageBatchAction("delete"); });
    }
    conversationQuickActionButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        var action = normalizeSpace(button.getAttribute("data-chat-quick-action"));
        if (action === "saved") {
          openSavedMessagesConversation();
          return;
        }
        if (action === "dm") {
          openDmCreationFlow();
          return;
        }
        if (action === "group") {
          openGroupCreationFlow();
        }
      });
    });
    placeholderActionButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        var action = normalizeSpace(button.getAttribute("data-placeholder-action"));
        if (action === "saved") {
          openSavedMessagesConversation();
          return;
        }
        if (action === "mandatory") {
          var conversation = primaryMandatoryConversation();
          if (!conversation) {
            showToast("هنوز گفت‌وگوی فعالی برای شروع وجود ندارد.");
            return;
          }
          openConversation(conversation.id, { forceFull: true, source: "placeholder" });
          return;
        }
        if (action === "dm") {
          openDmCreationFlow();
          return;
        }
        if (action === "group") {
          openGroupCreationFlow();
        }
      });
    });

    if (newPollLink) {
      newPollLink.addEventListener("click", function (event) {
        if (canOpenPollCenterForUser(state.me)) return;
        event.preventDefault();
        showToast("دسترسی به نظرسنجی فقط برای مالک یا نماینده فعال است.");
      });
    }

    if (dmSearch) {
      dmSearch.addEventListener("input", renderDmList);
    }

    if (groupSearch) {
      groupSearch.addEventListener("input", renderGroupMembersPicker);
    }

    if (groupCreateBtn) {
      groupCreateBtn.addEventListener("click", function () {
        createGroupConversation();
      });
    }

    if (groupStepNext) {
      groupStepNext.addEventListener("click", function () {
        if (!state.groupMemberSelection.size) {
          showToast("حداقل یک عضو برای ادامه انتخاب کن.");
          return;
        }
        setGroupCreateStep("details");
        if (groupTitleInput) {
          groupTitleInput.focus({ preventScroll: true });
        }
      });
    }

    if (groupStepBack) {
      groupStepBack.addEventListener("click", function () {
        setGroupCreateStep("members");
      });
    }

    if (groupSelectedMembers) {
      groupSelectedMembers.addEventListener("click", function (event) {
        var button = event.target.closest("[data-remove-member]");
        if (!button) return;
        var studentNumber = normalizeStudentNumber(button.getAttribute("data-remove-member"));
        if (!studentNumber) return;
        state.groupMemberSelection.delete(studentNumber);
        renderGroupMembersPicker();
      });
    }

    if (dmModalClose) dmModalClose.addEventListener("click", closeModal);
    if (groupModalClose) groupModalClose.addEventListener("click", closeModal);
    if (forwardModalClose) forwardModalClose.addEventListener("click", closeModal);
    if (reactionModalClose) reactionModalClose.addEventListener("click", closeModal);
    if (reactionDetailsModalClose) reactionDetailsModalClose.addEventListener("click", closeModal);
    if (receiptsModalClose) receiptsModalClose.addEventListener("click", closeModal);
    if (editModalClose) editModalClose.addEventListener("click", closeModal);
    if (editCancelBtn) editCancelBtn.addEventListener("click", closeModal);
    if (pollModalClose) pollModalClose.addEventListener("click", closeModal);
    if (pollCancelBtn) pollCancelBtn.addEventListener("click", closeModal);
    if (conversationOptionsClose) conversationOptionsClose.addEventListener("click", closeModal);
    if (confirmModalClose) {
      confirmModalClose.addEventListener("click", function () {
        resolveConfirmDialog(false);
        closeModal(true);
      });
    }
    if (confirmCancelBtn) {
      confirmCancelBtn.addEventListener("click", function () {
        resolveConfirmDialog(false);
        closeModal(true);
      });
    }
    if (confirmAcceptBtn) {
      confirmAcceptBtn.addEventListener("click", function () {
        resolveConfirmDialog(true);
        closeModal(true);
      });
    }
    if (editSaveBtn) editSaveBtn.addEventListener("click", saveEditedMessageFromModal);
    if (pollCreateBtn) pollCreateBtn.addEventListener("click", createPollFromModal);
    if (pollAddOptionBtn) {
      pollAddOptionBtn.addEventListener("click", function () {
        addPollOptionRow("", true);
      });
    }
    if (pollOptionsList) {
      pollOptionsList.addEventListener("click", function (event) {
        var removeBtn = event.target.closest("[data-poll-option-remove]");
        if (!removeBtn) return;
        var row = removeBtn.closest("[data-poll-option-row]");
        if (!row) return;
        if (pollOptionRows().length <= POLL_MIN_OPTIONS) return;
        row.remove();
        updatePollOptionControlsState();
      });
    }
    if (pollMultipleChoiceInput) {
      pollMultipleChoiceInput.addEventListener("change", togglePollMaxChoicesField);
    }
    if (modalBackdrop) modalBackdrop.addEventListener("click", closeModal);
    if (forwardSearch) {
      forwardSearch.addEventListener("input", renderForwardList);
    }
    if (forwardIncludeSenderInput) {
      forwardIncludeSenderInput.addEventListener("change", function () {
        state.forwardIncludeSenderName = !!forwardIncludeSenderInput.checked;
        renderForwardSourcePreview();
      });
    }
    if (reactionSearch) {
      reactionSearch.addEventListener("input", renderReactionPicker);
    }
    if (reactionNativeBtn) {
      reactionNativeBtn.addEventListener("click", function () {
        openNativeEmojiPicker();
      });
    }
    if (editTextInput) {
      editTextInput.addEventListener("keydown", function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
          event.preventDefault();
          saveEditedMessageFromModal();
        }
      });
    }

    if (threadInfoTrigger) {
      threadInfoTrigger.addEventListener("click", function () {
        openInfoSheet().catch(function (error) {
          console.error(error);
          showToast("باز کردن اطلاعات گفتگو انجام نشد.");
        });
      });
    }
    if (infoSheetClose) infoSheetClose.addEventListener("click", closeInfoSheet);
    if (infoSheetBackdrop) infoSheetBackdrop.addEventListener("click", function () {
      if (state.peerSheetOpen) { closePeerSheet(); } else { closeInfoSheet(); }
    });
    if (peerSheetClose) peerSheetClose.addEventListener("click", closePeerSheet);
    if (infoProfile) {
      infoProfile.addEventListener("click", function () {
        var href = infoProfile.dataset.profileHref;
        if (href) window.location.href = href;
      });
    }
    if (infoCopyLink) infoCopyLink.addEventListener("click", copyConversationLink);
    if (infoNotificationBtn) infoNotificationBtn.addEventListener("click", function () { openConversationOptions("notifications"); });
    if (infoEditProfileBtn) infoEditProfileBtn.addEventListener("click", function () { openConversationOptions("profile"); });
    if (infoGroupTypeBtn) infoGroupTypeBtn.addEventListener("click", function () { openConversationOptions("type"); });
    if (infoReactionSettingsBtn) infoReactionSettingsBtn.addEventListener("click", function () { openConversationOptions("reactions"); });
    if (infoAddMembersBtn) infoAddMembersBtn.addEventListener("click", function () { openConversationOptions("add-members"); });
    if (infoMembers) {
      infoMembers.addEventListener("click", function (event) {
        var menuButton = event.target && event.target.closest ? event.target.closest("[data-member-menu]") : null;
        if (menuButton) {
          event.stopPropagation();
          var conversation = activeConversation();
          var memberId = normalizeStudentNumber(menuButton.getAttribute("data-member-menu"));
          var member = conversation && (conversation.members || []).find(function (item) {
            return normalizeStudentNumber(item.studentNumber) === memberId;
          });
          if (member) {
            var rect = menuButton.getBoundingClientRect();
            openMemberContextMenu(member, rect.left + rect.width / 2, rect.bottom + 6);
          }
          return;
        }
        var memberRow = event.target && event.target.closest ? event.target.closest("[data-member-student]") : null;
        if (memberRow) {
          var studentNumber = memberRow.getAttribute("data-member-student");
          if (studentNumber) {
            var conversation = activeConversation();
            var members = conversation && Array.isArray(conversation.members) ? conversation.members : [];
            var member = members.find(function (item) {
              return normalizeStudentNumber(item.studentNumber) === normalizeStudentNumber(studentNumber);
            });
            if (member) {
              openChatMemberProfile(member);
            }
          }
        }
      });
    }
    if (infoContentTabs) {
      infoContentTabs.addEventListener("click", function (event) {
        var button = event.target && event.target.closest ? event.target.closest("[data-info-content]") : null;
        if (!button) return;
        state.infoContentCategory = normalizeSpace(button.getAttribute("data-info-content")) || "media";
        renderInfoContentOverview();
      });
    }
    if (infoContentTable) {
      infoContentTable.addEventListener("click", function (event) {
        var mediaButton = event.target && event.target.closest ? event.target.closest(".msg-attachment__media-btn[data-media-src]") : null;
        if (mediaButton) {
          event.preventDefault();
          openMediaViewerFromNode(mediaButton);
          return;
        }
        var button = event.target && event.target.closest ? event.target.closest("[data-scroll-message]") : null;
        if (!button) return;
        var messageId = Math.floor(toNumber(button.getAttribute("data-scroll-message"), 0));
        if (messageId > 0) {
          closeInfoSheet();
          scrollToMessage(messageId);
        }
      });
    }

    if (muteBtn) muteBtn.addEventListener("click", function () { setConversationMute(true); });
    if (unmuteBtn) unmuteBtn.addEventListener("click", function () { setConversationMute(false); });
    if (archiveBtn) archiveBtn.addEventListener("click", function () { setConversationArchive(true); });
    if (unarchiveBtn) unarchiveBtn.addEventListener("click", function () { setConversationArchive(false); });
    if (clearHistoryBtn) clearHistoryBtn.addEventListener("click", clearConversationHistory);
    if (leaveBtn) leaveBtn.addEventListener("click", leaveCurrentConversation);
    if (deleteConversationBtn) deleteConversationBtn.addEventListener("click", deleteCurrentConversation);
    if (unpinBtn) {
      unpinBtn.addEventListener("click", function () {
        var conversation = activeConversation();
        if (!conversation || !conversation.pinnedMessage) return;
        togglePin(conversation.pinnedMessage, false);
      });
    }

    if (pinnedWrap) {
      pinnedWrap.addEventListener("click", function () {
        var conversation = activeConversation();
        if (!conversation || !conversation.pinnedMessage) return;
        scrollToMessage(conversation.pinnedMessage.id);
      });
    }

    if (contextBackdrop) contextBackdrop.addEventListener("click", closeContextMenu);
    if (listContextBackdrop) listContextBackdrop.addEventListener("click", closeListContextMenu);
    bindMessageListDelegation();
    if (messagesEl) messagesEl.addEventListener("scroll", function () {
      if (state.contextOpen) closeContextMenu();
      if (state.listContextOpen) closeListContextMenu();
      state.threadAutoStick = isThreadNearBottom(56);
      maybeLoadOlderMessages();
    });

    if (chatTextEl) {
      chatTextEl.addEventListener("input", function () {
        autosizeComposer();
        handleComposerTypingActivity();
        refreshMentionSuggestions();
        scheduleComposerDraftSave();
      });
      chatTextEl.addEventListener("keydown", function (event) {
        if (Array.isArray(state.mentionSuggestions) && state.mentionSuggestions.length) {
          if (event.key === "ArrowDown") {
            event.preventDefault();
            moveMentionSelection(1);
            return;
          }
          if (event.key === "ArrowUp") {
            event.preventDefault();
            moveMentionSelection(-1);
            return;
          }
          if (event.key === "Tab" || (event.key === "Enter" && !event.shiftKey)) {
            event.preventDefault();
            applyMentionSuggestionAtIndex(state.mentionSelectedIndex >= 0 ? state.mentionSelectedIndex : 0);
            return;
          }
          if (event.key === "Escape") {
            event.preventDefault();
            closeMentionSuggestions();
            return;
          }
        }
        if (event.key === "Enter" && !event.shiftKey) {
          event.preventDefault();
          sendCurrentMessage();
        }
      });
      chatTextEl.addEventListener("click", refreshMentionSuggestions);
      chatTextEl.addEventListener("focus", function () {
        applyViewportHeight();
        syncFocusedComposerIntoView();
        window.setTimeout(syncFocusedComposerIntoView, 140);
        window.setTimeout(syncFocusedComposerIntoView, 320);
        schedulePresenceHeartbeat(0);
        refreshMentionSuggestions();
      });
      chatTextEl.addEventListener("blur", function () {
        window.setTimeout(applyViewportHeight, 160);
        clearTypingActivity(false);
        window.setTimeout(closeMentionSuggestions, 120);
      });
      chatTextEl.addEventListener("paste", function (event) {
        var items = event.clipboardData ? event.clipboardData.items : null;
        if (!items || !items.length || !canQueueAttachments()) return;
        var files = [];
        for (var i = 0; i < items.length; i += 1) {
          if (items[i].kind === "file") {
            var file = items[i].getAsFile();
            if (file) files.push(file);
          }
        }
        if (!files.length) return;
        event.preventDefault();
        files.forEach(function (file, index) {
          if (!file.name || file.name === "blob") {
            var ext = (file.type && file.type.split("/")[1]) || "png";
            file = new File([file], "pasted-" + Date.now() + "-" + index + "." + ext, { type: file.type });
          }
          queueAttachmentUpload(file, {}).catch(function () {});
        });
      });
    }

    if (sendBtn) sendBtn.addEventListener("click", sendCurrentMessage);
    window.addEventListener("dent1402:offline-queue-success", function (event) {
      var detail = event && event.detail ? event.detail : {};
      var entry = detail.entry || null;
      if (!entry || entry.kind !== "chat-send") {
        return;
      }
      if (String(entry.meta && entry.meta.conversationId || "") !== String(state.activeConversationId || "")) {
        return;
      }
      if (applyQueuedChatSendPayload(detail.payload || null)) {
        showToast("پیام آفلاین ارسال شد.");
      }
    });
    if (attachBtn) {
      attachBtn.addEventListener("click", function () {
        var conversation = activeConversation();
        if (!conversation) {
          showToast("ابتدا یک گفت‌وگو را انتخاب کن.");
          return;
        }
        if (conversation.permissions && conversation.permissions.canSend === false) {
          showToast("در این گفت‌وگو اجازه ارسال فایل ندارید.");
          return;
        }
        if (conversation.settings && conversation.settings.muted) {
          showToast("ارسال پیام در این گفت‌وگو بسته است.");
          return;
        }
        setUploadSheetOpen(composerUploadSheet ? composerUploadSheet.hidden : false);
      });
    }
    if (composerUploadSheet) {
      composerUploadSheet.addEventListener("click", function (event) {
        var target = event.target.closest("[data-attach-accept]");
        if (!target) return;
        var accept = normalizeSpace(target.getAttribute("data-attach-accept")) || "*/*";
        var label = normalizeSpace(target.getAttribute("data-attach-label")) || "فایل";
        setUploadSheetOpen(false); setEmojiPanelOpen(false);
        pickAttachmentFiles(accept, label);
      });
    }
    if (emojiBtn) {
      emojiBtn.addEventListener("click", function () {
        if (emojiBtn.disabled) return;
        var willOpen = emojiPanel ? emojiPanel.hidden : false;
        setUploadSheetOpen(false); setEmojiPanelOpen(false);
        setEmojiPanelOpen(willOpen);
      });
    }
    if (pollBtn) {
      pollBtn.addEventListener("click", function () {
        if (pollBtn.disabled) return;
        openPollComposerModal();
      });
    }
    if (emojiTabs) {
      emojiTabs.addEventListener("click", function (event) {
        var target = event.target.closest("[data-emoji-category]");
        if (!target) return;
        setEmojiCategory(target.getAttribute("data-emoji-category"));
      });
    }
    if (emojiGrid) {
      emojiGrid.addEventListener("click", function (event) {
        var target = event.target.closest("[data-emoji]");
        if (!target) return;
        insertEmojiAtCursor(target.getAttribute("data-emoji"));
      });
    }
    if (attachmentInput) {
      attachmentInput.addEventListener("change", function () {
        var fileList = attachmentInput.files ? Array.from(attachmentInput.files) : [];
        attachmentInput.value = "";
        if (!fileList.length) return;
        fileList.forEach(function (file) {
          queueAttachmentUpload(file, {}).catch(function () {});
        });
      });
    }
    if (threadShell) {
      var chatDragDepth = 0;
      var dragHasFiles = function (event) {
        return !!(event.dataTransfer && Array.from(event.dataTransfer.types || []).indexOf("Files") !== -1);
      };
      threadShell.addEventListener("dragenter", function (event) {
        if (!dragHasFiles(event) || !canQueueAttachments()) return;
        event.preventDefault();
        chatDragDepth += 1;
        if (chatDropOverlay) chatDropOverlay.hidden = false;
      });
      threadShell.addEventListener("dragover", function (event) {
        if (!dragHasFiles(event) || !canQueueAttachments()) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = "copy";
      });
      threadShell.addEventListener("dragleave", function () {
        if (!chatDropOverlay || chatDropOverlay.hidden) return;
        chatDragDepth = Math.max(0, chatDragDepth - 1);
        if (chatDragDepth === 0) chatDropOverlay.hidden = true;
      });
      threadShell.addEventListener("drop", function (event) {
        chatDragDepth = 0;
        if (chatDropOverlay) chatDropOverlay.hidden = true;
        if (!dragHasFiles(event)) return;
        event.preventDefault();
        if (!canQueueAttachments()) return;
        var fileList = event.dataTransfer.files ? Array.from(event.dataTransfer.files) : [];
        fileList.forEach(function (file) {
          queueAttachmentUpload(file, {}).catch(function () {});
        });
      });
    }
    if (composerUploads) {
      composerUploads.addEventListener("click", function (event) {
        var previewButton = event.target.closest("[data-upl-preview]");
        if (previewButton) {
          openMediaViewerFromNode(previewButton);
          return;
        }
        var editButton = event.target.closest("[data-upl-edit]");
        if (editButton) {
          openImageEditor(editButton.getAttribute("data-upl-edit"));
          return;
        }
        var moveButton = event.target.closest("[data-upl-move]");
        if (moveButton) {
          moveComposerAttachment(
            moveButton.getAttribute("data-upl-move"),
            Math.floor(toNumber(moveButton.getAttribute("data-upl-shift"), 0)) || 0
          );
          return;
        }
        var button = event.target.closest("[data-upl-remove]");
        if (!button) return;
        removeComposerAttachmentByLocalId(button.getAttribute("data-upl-remove"));
      });
    }
    if (imageEditorClose) {
      imageEditorClose.addEventListener("click", closeImageEditor);
    }
    if (imageEditorDone) {
      imageEditorDone.addEventListener("click", finishImageEditor);
    }
    if (imageEditorRotate) {
      imageEditorRotate.addEventListener("click", rotateImageEditorImage);
    }
    if (imageEditorCrop) {
      imageEditorCrop.addEventListener("click", function () {
        setImageEditorMode("crop");
      });
    }
    if (imageEditorDraw) {
      imageEditorDraw.addEventListener("click", function () {
        setImageEditorMode("draw");
      });
    }
    if (imageEditorFilter) {
      imageEditorFilter.addEventListener("click", cycleImageEditorFilter);
    }
    if (imageEditorDrawOptions) {
      imageEditorDrawOptions.addEventListener("click", function (event) {
        var colorButton = event.target.closest("[data-draw-color]");
        if (!colorButton || !imageEditorState) return;
        imageEditorState.drawColor = colorButton.getAttribute("data-draw-color");
        Array.from(imageEditorDrawOptions.querySelectorAll(".chat-image-editor__color")).forEach(function (btn) {
          btn.classList.toggle("is-selected", btn === colorButton);
        });
      });
    }
    if (imageEditorCanvas) {
      imageEditorCanvas.addEventListener("pointerdown", function (event) {
        if (!imageEditorState || imageEditorState.mode !== "draw") return;
        event.preventDefault();
        var point = imageEditorCanvasPoint(event);
        imageEditorState.drawPointerId = event.pointerId;
        imageEditorState.lastDrawPoint = point;
        try {
          imageEditorCanvas.setPointerCapture(event.pointerId);
        } catch (_error) {
          // Ignore pointer capture failures.
        }
        drawImageEditorStroke(point, point);
      });
      imageEditorCanvas.addEventListener("pointermove", function (event) {
        if (!imageEditorState || imageEditorState.drawPointerId !== event.pointerId) return;
        event.preventDefault();
        var point = imageEditorCanvasPoint(event);
        drawImageEditorStroke(imageEditorState.lastDrawPoint, point);
        imageEditorState.lastDrawPoint = point;
      });
      var endImageEditorStroke = function (event) {
        if (!imageEditorState || imageEditorState.drawPointerId !== event.pointerId) return;
        imageEditorState.drawPointerId = null;
        imageEditorState.lastDrawPoint = null;
      };
      imageEditorCanvas.addEventListener("pointerup", endImageEditorStroke);
      imageEditorCanvas.addEventListener("pointercancel", endImageEditorStroke);
    }
    if (imageEditorCropBox) {
      Array.from(imageEditorCropBox.querySelectorAll("[data-handle]")).forEach(function (handle) {
        var handleName = handle.getAttribute("data-handle");
        handle.addEventListener("pointerdown", function (event) {
          if (!imageEditorState || imageEditorState.mode !== "crop") return;
          event.preventDefault();
          event.stopPropagation();
          try {
            handle.setPointerCapture(event.pointerId);
          } catch (_error) {
            // Ignore pointer capture failures.
          }
          imageEditorState.cropDrag = {
            pointerId: event.pointerId,
            handle: handleName,
            startClientX: event.clientX,
            startClientY: event.clientY,
            startRect: {
              x: imageEditorState.cropRect.x,
              y: imageEditorState.cropRect.y,
              w: imageEditorState.cropRect.w,
              h: imageEditorState.cropRect.h
            }
          };
        });
        handle.addEventListener("pointermove", function (event) {
          var drag = imageEditorState && imageEditorState.cropDrag;
          if (!drag || drag.pointerId !== event.pointerId) return;
          event.preventDefault();
          var displayRect = imageEditorCanvasDisplayRect();
          if (!displayRect.width || !displayRect.height) return;
          var source = imageEditorState.source;
          var scaleX = source.width / displayRect.width;
          var scaleY = source.height / displayRect.height;
          var dx = (event.clientX - drag.startClientX) * scaleX;
          var dy = (event.clientY - drag.startClientY) * scaleY;
          var minSize = Math.min(source.width, source.height) * 0.1;
          imageEditorState.cropRect = imageEditorCropRectForHandle(drag.startRect, drag.handle, dx, dy, source.width, source.height, minSize);
          updateImageEditorCropBoxStyle();
        });
        var endCropDrag = function (event) {
          var drag = imageEditorState && imageEditorState.cropDrag;
          if (!drag || drag.pointerId !== event.pointerId) return;
          imageEditorState.cropDrag = null;
        };
        handle.addEventListener("pointerup", endCropDrag);
        handle.addEventListener("pointercancel", endCropDrag);
      });
    }
    if (threadSearchToggle) {
      threadSearchToggle.addEventListener("click", function () {
        setThreadSearchOpen(!state.threadSearchOpen);
      });
    }
    if (threadSearchClose) {
      threadSearchClose.addEventListener("click", function () {
        setThreadSearchOpen(false);
      });
    }
    if (threadSearchInput) {
      threadSearchInput.addEventListener("input", function () {
        state.threadSearchQuery = normalizeSpace(threadSearchInput.value || "");
        if (!state.threadSearchQuery) {
          if (state.threadSearchDebounceTimer) {
            window.clearTimeout(state.threadSearchDebounceTimer);
            state.threadSearchDebounceTimer = null;
          }
          state.threadSearchResults = [];
          state.threadSearchIndex = -1;
          updateThreadSearchUi();
          return;
        }
        scheduleThreadSearch();
        updateThreadSearchUi();
      });
      threadSearchInput.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
          event.preventDefault();
          goToThreadSearchResult(state.threadSearchIndex >= 0 ? state.threadSearchIndex : 0).catch(function (error) {
            showToast((error && error.message) || "جست‌وجوی گفتگو انجام نشد.");
          });
          return;
        }
        if (event.key === "Escape") {
          event.preventDefault();
          setThreadSearchOpen(false);
        }
      });
    }
    if (threadSearchPrev) {
      threadSearchPrev.addEventListener("click", function () {
        goToThreadSearchResult(state.threadSearchIndex - 1).catch(function (error) {
          showToast((error && error.message) || "جست‌وجوی گفتگو انجام نشد.");
        });
      });
    }
    if (threadSearchNext) {
      threadSearchNext.addEventListener("click", function () {
        goToThreadSearchResult(state.threadSearchIndex + 1).catch(function (error) {
          showToast((error && error.message) || "جست‌وجوی گفتگو انجام نشد.");
        });
      });
    }
    if (threadSearchPreview) {
      threadSearchPreview.addEventListener("click", function () {
        var result = currentThreadSearchResult();
        if (!result) return;
        loadThreadContext(result.messageId, { behavior: "smooth", block: "center", durationMs: 2200 }).catch(function (error) {
          showToast((error && error.message) || "پرش به پیام انجام نشد.");
        });
      });
    }
    if (threadDaySelect) {
      threadDaySelect.addEventListener("change", updateThreadSearchUi);
    }
    if (threadJumpDayBtn) {
      threadJumpDayBtn.addEventListener("click", function () {
        var messageId = Math.max(0, Math.floor(toNumber(threadDaySelect && threadDaySelect.value, 0)));
        if (!messageId) return;
        loadThreadContext(messageId, { behavior: "smooth", block: "start", durationMs: 1800 }).catch(function (error) {
          showToast((error && error.message) || "پرش به روز موردنظر انجام نشد.");
        });
      });
    }
    if (threadJumpUnreadBtn) {
      threadJumpUnreadBtn.addEventListener("click", function () {
        var messageId = Math.max(0, Math.floor(toNumber(state.threadNavigatorFirstUnreadMessageId || state.unreadDividerMessageId, 0)));
        if (!messageId) return;
        loadThreadContext(messageId, { behavior: "smooth", block: "center", durationMs: 2200 }).catch(function (error) {
          showToast((error && error.message) || "پرش به اولین پیام خوانده‌نشده انجام نشد.");
        });
      });
    }
    if (voiceBtn) {
      voiceBtn.addEventListener("pointerdown", function (event) {
        if (!touchLikePointer(event.pointerType)) return;
        if (event.button != null && event.button !== 0) return;
        if (state.voiceRecorder || state.voiceGesture) return;
        event.preventDefault();
        state.voiceGesture = {
          pointerId: event.pointerId,
          startX: event.clientX,
          startY: event.clientY,
          lastX: event.clientX,
          lastY: event.clientY,
          releasedBeforeReady: false,
          releaseMode: ""
        };
        voiceBtn.__skipClickUntil = Date.now() + 520;
        if (typeof voiceBtn.setPointerCapture === "function") {
          try {
            voiceBtn.setPointerCapture(event.pointerId);
          } catch (_error) {}
        }
        startVoiceRecording({ gestureMode: true }).catch(function () {});
      });
      voiceBtn.addEventListener("pointermove", function (event) {
        if (!state.voiceGesture || event.pointerId !== state.voiceGesture.pointerId) return;
        state.voiceGesture.lastX = event.clientX;
        state.voiceGesture.lastY = event.clientY;
        updateVoiceGesturePosition(event.clientX, event.clientY);
      });
      var releaseVoicePointer = function (mode) {
        return function (event) {
          if (!state.voiceGesture || event.pointerId !== state.voiceGesture.pointerId) return;
          state.voiceGesture.lastX = event.clientX;
          state.voiceGesture.lastY = event.clientY;
          if (typeof voiceBtn.releasePointerCapture === "function") {
            try {
              voiceBtn.releasePointerCapture(event.pointerId);
            } catch (_error) {}
          }
          if (!state.voiceRecorder) {
            state.voiceGesture.releasedBeforeReady = true;
            state.voiceGesture.releaseMode = mode || "";
            return;
          }
          releaseVoiceGesture(mode || "");
        };
      };
      voiceBtn.addEventListener("pointerup", releaseVoicePointer(""));
      voiceBtn.addEventListener("pointercancel", releaseVoicePointer("cancel"));
      voiceBtn.addEventListener("click", function () {
        if (voiceBtn.__skipClickUntil && voiceBtn.__skipClickUntil > Date.now()) {
          return;
        }
        startVoiceRecording({ gestureMode: false }).catch(function () {});
      });
    }
    if (voiceStopBtn) {
      voiceStopBtn.addEventListener("click", function () {
        stopVoiceRecording(false);
      });
    }
    if (voiceCancelBtn) {
      voiceCancelBtn.addEventListener("click", function () {
        resetVoiceRecorder();
        setComposerStatus("", "");
      });
    }
    if (voiceSendBtn) {
      voiceSendBtn.addEventListener("click", function () {
        stopVoiceRecording(true);
      });
    }
    if (replyCancel) replyCancel.addEventListener("click", clearReplyTarget);
    if (mediaViewerClose) mediaViewerClose.addEventListener("click", closeMediaViewer);
    if (mediaViewerRotate) {
      mediaViewerRotate.addEventListener("click", function (event) {
        event.preventDefault();
        rotateMediaViewerImage();
      });
    }
    if (mediaViewerForward) {
      mediaViewerForward.addEventListener("click", function (event) {
        event.preventDefault();
        var item = state.mediaViewerItems[state.mediaViewerIndex];
        var message = item && item.messageId ? findMessage(item.messageId) : null;
        if (!message) return;
        closeMediaViewer();
        openForwardPicker(message);
      });
    }
    if (mediaViewerDelete) {
      mediaViewerDelete.addEventListener("click", function (event) {
        event.preventDefault();
        var item = state.mediaViewerItems[state.mediaViewerIndex];
        var message = item && item.messageId ? findMessage(item.messageId) : null;
        if (!message) return;
        deleteMessage(message).then(function () {
          if (!state.messages.has(message.id)) closeMediaViewer();
        });
      });
    }
    if (mediaViewer) {
      mediaViewer.addEventListener("click", function (event) {
        if (event.target === mediaViewer) closeMediaViewer();
      });
      mediaViewer.addEventListener("pointerdown", function (event) {
        if (event.pointerType === "mouse" && event.button !== 0) return;
        if (event.target && event.target.closest && event.target.closest(".chat-media-viewer__nav, .chat-media-viewer__close, .chat-media-viewer__action, .chat-media-viewer__meta")) return;
        if (!mediaViewerStage || !mediaViewerStage.contains(event.target)) return;
        if (event.target && event.target.tagName === "VIDEO") return;

        state.mediaViewerActivePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        if (typeof mediaViewer.setPointerCapture === "function") {
          try {
            mediaViewer.setPointerCapture(event.pointerId);
          } catch (_error) {}
        }

        if (state.mediaViewerActivePointers.size === 2) {
          state.mediaViewerPointer = null;
          state.mediaViewerPanPointer = null;
          resetMediaViewerStageOffset();
          var pts = Array.from(state.mediaViewerActivePointers.values());
          state.mediaViewerPinch = {
            startDistance: Math.max(1, Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y)),
            startScale: state.mediaViewerScale
          };
          return;
        }
        if (state.mediaViewerActivePointers.size > 2) return;

        if (state.mediaViewerScale > 1.01) {
          state.mediaViewerPanPointer = {
            id: event.pointerId,
            lastX: event.clientX,
            lastY: event.clientY
          };
          return;
        }

        if (state.mediaViewerItems.length <= 1) return;
        state.mediaViewerPointer = {
          id: event.pointerId,
          startX: event.clientX,
          startY: event.clientY,
          lastX: event.clientX,
          lastY: event.clientY,
          dragging: false
        };
      });
      mediaViewer.addEventListener("pointermove", function (event) {
        if (state.mediaViewerActivePointers.has(event.pointerId)) {
          state.mediaViewerActivePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        }

        if (state.mediaViewerPinch && state.mediaViewerActivePointers.size >= 2) {
          var pts = Array.from(state.mediaViewerActivePointers.values()).slice(0, 2);
          var dist = Math.max(1, Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y));
          event.preventDefault();
          var midX = (pts[0].x + pts[1].x) / 2;
          var midY = (pts[0].y + pts[1].y) / 2;
          setMediaViewerScaleAtPoint(state.mediaViewerPinch.startScale * (dist / state.mediaViewerPinch.startDistance), midX, midY, { live: true });
          return;
        }

        if (state.mediaViewerPanPointer && state.mediaViewerPanPointer.id === event.pointerId) {
          var panDx = event.clientX - state.mediaViewerPanPointer.lastX;
          var panDy = event.clientY - state.mediaViewerPanPointer.lastY;
          state.mediaViewerPanPointer.lastX = event.clientX;
          state.mediaViewerPanPointer.lastY = event.clientY;
          state.mediaViewerOffsetX += panDx;
          state.mediaViewerOffsetY += panDy;
          event.preventDefault();
          applyMediaViewerTransform({ live: true });
          return;
        }

        var start = state.mediaViewerPointer;
        if (!start || start.id !== event.pointerId) return;
        start.lastX = event.clientX;
        start.lastY = event.clientY;
        var dx = event.clientX - start.startX;
        var dy = event.clientY - start.startY;
        if (!start.dragging) {
          if (Math.abs(dx) < 10) return;
          if (Math.abs(dx) < Math.abs(dy) * 1.1) return;
          start.dragging = true;
        }
        event.preventDefault();
        applyMediaViewerStageOffset(dx);
      });
      mediaViewer.addEventListener("pointerup", function (event) {
        state.mediaViewerActivePointers.delete(event.pointerId);
        if (typeof mediaViewer.releasePointerCapture === "function") {
          try {
            mediaViewer.releasePointerCapture(event.pointerId);
          } catch (_error) {}
        }

        if (state.mediaViewerPinch) {
          if (state.mediaViewerActivePointers.size < 2) {
            state.mediaViewerPinch = null;
            setMediaViewerScale(state.mediaViewerScale);
          }
          return;
        }

        if (state.mediaViewerPanPointer && state.mediaViewerPanPointer.id === event.pointerId) {
          state.mediaViewerPanPointer = null;
          return;
        }

        var start = state.mediaViewerPointer;
        state.mediaViewerPointer = null;
        if (!start || start.id !== event.pointerId) return;
        var dx = event.clientX - start.startX;
        var dy = event.clientY - start.startY;
        if (!start.dragging) {
          resetMediaViewerStageOffset();
          return;
        }
        if (Math.abs(dx) < Math.min(96, window.innerWidth * 0.16) || Math.abs(dx) < Math.abs(dy) * 1.1) {
          resetMediaViewerStageOffset();
          return;
        }
        settleMediaViewerStep(dx < 0 ? 1 : -1, dx);
      });
      mediaViewer.addEventListener("pointercancel", function (event) {
        state.mediaViewerActivePointers.delete(event.pointerId);
        if (state.mediaViewerPinch && state.mediaViewerActivePointers.size < 2) {
          state.mediaViewerPinch = null;
          setMediaViewerScale(state.mediaViewerScale);
        }
        if (state.mediaViewerPanPointer && state.mediaViewerPanPointer.id === event.pointerId) {
          state.mediaViewerPanPointer = null;
        }
        state.mediaViewerPointer = null;
        resetMediaViewerStageOffset();
      });
    }
    if (mediaViewerStage) {
      mediaViewerStage.addEventListener("dblclick", function (event) {
        event.preventDefault();
        toggleMediaViewerZoom(event);
      });
      mediaViewerStage.addEventListener("wheel", function (event) {
        var item = state.mediaViewerItems[state.mediaViewerIndex];
        if (!item || item.kind !== "image") return;
        event.preventDefault();
        var factor = Math.exp(-event.deltaY * 0.0015);
        setMediaViewerScale(state.mediaViewerScale * factor, { live: true });
      }, { passive: false });
    }

    if (logoutBtn) {
      logoutBtn.addEventListener("click", function () {
        var auth = safeAuthApi();
        if (!auth || typeof auth.logout !== "function") {
          window.location.href = "/account/";
          return;
        }
        logoutBtn.disabled = true;
        auth.logout().catch(function () {}).finally(function () {
          logoutBtn.disabled = false;
        });
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener("click", function () {
        syncConversation({
          forceFull: true,
          includeMembers: state.infoSheetOpen,
          silent: false
        }).catch(function (error) {
          showToast(error && error.message ? error.message : "بازخوانی گفتگو انجام نشد.");
        });
      });
    }

    if (mobileOpenListBtn) mobileOpenListBtn.addEventListener("click", openListPane);
    if (mobileCloseListBtn) {
      mobileCloseListBtn.addEventListener("click", function () {
        if (state.activeConversationId) {
          openThreadPane();
        } else {
          openListPane();
        }
      });
    }

    if (mobileNewChatFab) {
      mobileNewChatFab.addEventListener("click", function () {
        openDmCreationFlow();
      });
    }

    if (chatNavList) {
      chatNavList.addEventListener("click", function () {
        if (state.modalOpen) {
          closeModal();
        }
        openListPane();
      });
    }
    if (chatNavCompose) {
      chatNavCompose.addEventListener("click", function () {
        openDmCreationFlow();
      });
    }
    if (chatNavGroup) {
      chatNavGroup.addEventListener("click", function () {
        openGroupCreationFlow();
      });
    }
    if (chatNavPolls) {
      chatNavPolls.addEventListener("click", function (event) {
        if (canOpenPollCenterForUser(state.me)) return;
        event.preventDefault();
        showToast("دسترسی به نظرسنجی فقط برای مالک یا نماینده فعال است.");
      });
    }

    document.addEventListener("keydown", function (event) {
      if (mediaViewer && !mediaViewer.hidden) {
        if (event.key === "Escape") {
          closeMediaViewer();
          return;
        }
        if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
          stepMediaViewer(event.key === "ArrowLeft" ? 1 : -1);
          return;
        }
      }
      if (event.key !== "Escape") return;
      if (state.threadSearchOpen) {
        setThreadSearchOpen(false);
        return;
      }
      if (composerUploadSheet && !composerUploadSheet.hidden) {
        setUploadSheetOpen(false); setEmojiPanelOpen(false);
      }
      if (emojiPanel && !emojiPanel.hidden) {
        setEmojiPanelOpen(false);
      }
      if (state.contextOpen) {
        closeContextMenu();
        return;
      }
      if (state.infoSheetOpen) {
        closeInfoSheet();
        return;
      }
      if (state.modalOpen) {
        closeModal();
      }
    });

    document.addEventListener("click", function (event) {
      var target = event.target;
      if (!target) return;
      if (composerUploadSheet && !composerUploadSheet.hidden) {
        if (!composerUploadSheet.contains(target) && !(attachBtn && attachBtn.contains(target))) {
          setUploadSheetOpen(false); setEmojiPanelOpen(false);
        }
      }
      if (emojiPanel && !emojiPanel.hidden) {
        if (!emojiPanel.contains(target) && !(emojiBtn && emojiBtn.contains(target))) {
          setEmojiPanelOpen(false);
        }
      }
    });

    window.addEventListener("resize", function () {
      queueViewportRefresh(true);
      if (isKeyboardViewportShift()) {
        updateMobileNav();
        return;
      }
      if (!isMobileViewport()) {
        setMobileView("list");
      } else if (state.modalOpen) {
        if (state.modalOpen === "group") {
          setMobileView("group");
        } else if (state.modalOpen === "dm" || state.modalOpen === "forward") {
          setMobileView("dm");
        } else if (state.activeConversationId) {
          setMobileView("thread");
        } else {
          setMobileView("list");
        }
      } else {
        // On mobile, list view should remain the default after layout recalculations.
        var currentView = chatApp && chatApp.dataset ? normalizeSpace(chatApp.dataset.mobileView) : "";
        if (currentView === "thread" && state.activeConversationId) {
          setMobileView("thread");
        } else {
          setMobileView("list");
        }
      }
      closeContextMenu();
    });

    if (window.visualViewport) {
      window.visualViewport.addEventListener("resize", function () {
        queueViewportRefresh(true);
      }, { passive: true });
      window.visualViewport.addEventListener("scroll", function () {
        queueViewportRefresh(true);
      }, { passive: true });
    }

    window.addEventListener("focus", function () {
      if (!state.me.loggedIn) return;
      syncConversation({ forceFull: false, includeMembers: state.infoSheetOpen, silent: true }).catch(function () {});
      loadNotificationBadgeSummary(false);
      schedulePresenceHeartbeat(0);
    });

    document.addEventListener("visibilitychange", function () {
      if (!state.me.loggedIn) return;
      if (document.hidden) {
        clearPresenceHeartbeatTimer();
        clearTypingActivity(false);
        stopPolling();
        return;
      }
      refreshTransportBinding();
      syncConversation({ forceFull: false, includeMembers: state.infoSheetOpen, silent: true }).catch(function () {});
      loadNotificationBadgeSummary(false);
      schedulePresenceHeartbeat(0);
    });

    window.addEventListener("dent1402:notifications-change", function (event) {
      var detail = event && event.detail ? event.detail : {};
      navBadgeState.notificationsLastUserKey = normalizeStudentNumber(state.me && state.me.studentNumber);
      navBadgeState.notificationsLastFetchedAt = Date.now();
      navBadgeState.notificationsUnread = Math.max(0, Math.floor(toNumber(detail.unreadCount, 0)));
      updateChatNavBadges();
    });
  }


  function boot() {
    applyViewportHeight();
    syncThemeColor();
    autosizeComposer();
    renderComposerUploads();
    updateVoiceUi();
    renderEmojiTabs();
    renderEmojiGrid();
    setUploadSheetOpen(false); setEmojiPanelOpen(false);
    setThreadVisible(false);
    setMobileView("list");
    setConnectionState("idle", "آفلاین");
    showStreamState("loading", "در حال آماده‌سازی...", "در حال بررسی وضعیت نشست.");
    updateConversationFilterTabs();
    updateConversationMeta();
    renderConversationList();
    updateThreadHead();
    updateComposerState();
    updatePinnedUi();
    updateMuteUi({ muted: false });
    syncCurrentUserAvatar();
    syncMobileNavLinks();
    updateMobileNav();
    updateFabVisibility();
    installChatOverscrollGuard();
    bindEvents();
    if (window.matchMedia) {
      var darkMedia = window.matchMedia("(prefers-color-scheme: dark)");
      if (typeof darkMedia.addEventListener === "function") {
        darkMedia.addEventListener("change", syncThemeColor);
      } else if (typeof darkMedia.addListener === "function") {
        darkMedia.addListener(syncThemeColor);
      }
    }
    if (typeof MutationObserver === "function") {
      var themeObserver = new MutationObserver(function () {
        syncThemeColor();
      });
      themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["data-theme", "class"]
      });
    }

    var auth = safeAuthApi();
    if (!auth || typeof auth.onChange !== "function") {
      resetLoggedOutUi("وضعیت احراز هویت بارگذاری نشد.");
      return;
    }

    auth.onChange(function (detail) {
      Promise.resolve(handleAuthChange(detail)).catch(function (error) {
        console.error(error);
      });
    });
  }

  boot();
})();
