/* Tiny FormatPainter plugin
 *
 * Copyright 2010-2018 Tiny Technologies LLC. All rights reserved.
 *
 * Version: 1.1.0-23
 */

!function(l) {
    "use strict";
    var e, r, n, t, o, i, m, a, d, u, c, s, v, f, p = function(e) {
        var r = e
          , n = function() {
            return r
        };
        return {
            get: n,
            set: function(e) {
                r = e
            },
            clone: function() {
                return p(n())
            }
        }
    }, g = function(e) {
        return parseInt(e, 10)
    }, h = function(e, r, n) {
        return {
            major: e,
            minor: r,
            patch: n
        }
    }, b = function(e) {
        var r = /([0-9]+)\.([0-9]+)\.([0-9]+)(?:(\-.+)?)/.exec(e);
        return r ? h(g(r[1]), g(r[2]), g(r[3])) : h(0, 0, 0)
    }, y = function(e, r) {
        var n = e - r;
        return 0 === n ? 0 : 0 < n ? 1 : -1
    }, S = function(e, r) {
        return !!e && -1 === function(e, r) {
            var n = y(e.major, r.major);
            if (0 !== n)
                return n;
            var t = y(e.minor, r.minor);
            if (0 !== t)
                return t;
            var o = y(e.patch, r.patch);
            return 0 !== o ? o : 0
        }(b([(n = e).majorVersion, n.minorVersion].join(".").split(".").slice(0, 3).join(".")), b(r));
        var n
    }, O = function(e) {
        return function() {
            return e
        }
    }, w = O(!1), N = O(!0), T = w, x = N, E = function() {
        return k
    }, k = (t = {
        fold: function(e, r) {
            return e()
        },
        is: T,
        isSome: T,
        isNone: x,
        getOr: n = function(e) {
            return e
        }
        ,
        getOrThunk: r = function(e) {
            return e()
        }
        ,
        getOrDie: function(e) {
            throw new Error(e || "error: getOrDie called on none.")
        },
        getOrNull: function() {
            return null
        },
        getOrUndefined: function() {},
        or: n,
        orThunk: r,
        map: E,
        ap: E,
        each: function() {},
        bind: E,
        flatten: E,
        exists: T,
        forall: x,
        filter: E,
        equals: e = function(e) {
            return e.isNone()
        }
        ,
        equals_: e,
        toArray: function() {
            return []
        },
        toString: O("none()")
    },
    Object.freeze && Object.freeze(t),
    t), A = function(n) {
        var e = function() {
            return n
        }
          , r = function() {
            return o
        }
          , t = function(e) {
            return e(n)
        }
          , o = {
            fold: function(e, r) {
                return r(n)
            },
            is: function(e) {
                return n === e
            },
            isSome: x,
            isNone: T,
            getOr: e,
            getOrThunk: e,
            getOrDie: e,
            getOrNull: e,
            getOrUndefined: e,
            or: r,
            orThunk: r,
            map: function(e) {
                return A(e(n))
            },
            ap: function(e) {
                return e.fold(E, function(e) {
                    return A(e(n))
                })
            },
            each: function(e) {
                e(n)
            },
            bind: t,
            flatten: e,
            exists: t,
            forall: t,
            filter: function(e) {
                return e(n) ? o : k
            },
            equals: function(e) {
                return e.is(n)
            },
            equals_: function(e, r) {
                return e.fold(T, function(e) {
                    return r(n, e)
                })
            },
            toArray: function() {
                return [n]
            },
            toString: function() {
                return "some(" + n + ")"
            }
        };
        return o
    }, _ = {
        some: A,
        none: E,
        from: function(e) {
            return null == e ? k : A(e)
        }
    }, D = function(r) {
        return function(e) {
            return function(e) {
                if (null === e)
                    return "null";
                var r = typeof e;
                return "object" === r && Array.prototype.isPrototypeOf(e) ? "array" : "object" === r && String.prototype.isPrototypeOf(e) ? "string" : r
            }(e) === r
        }
    }, C = D("string"), L = D("boolean"), R = D("function"), P = D("number"), F = void 0 === (o = Array.prototype.indexOf) ? function(e, r) {
        return q(e, r)
    }
    : function(e, r) {
        return o.call(e, r)
    }
    , I = function(e, r) {
        return -1 < F(e, r)
    }, j = function(e, r) {
        return V(e, r).isSome()
    }, M = function(e, r) {
        for (var n = e.length, t = new Array(n), o = 0; o < n; o++) {
            var i = e[o];
            t[o] = r(i, o, e)
        }
        return t
    }, B = function(e, r) {
        for (var n = [], t = 0, o = e.length; t < o; t++) {
            var i = e[t];
            r(i, t, e) && n.push(i)
        }
        return n
    }, U = function(e, r) {
        for (var n = 0, t = e.length; n < t; n++) {
            var o = e[n];
            if (r(o, n, e))
                return _.some(o)
        }
        return _.none()
    }, V = function(e, r) {
        for (var n = 0, t = e.length; n < t; n++)
            if (r(e[n], n, e))
                return _.some(n);
        return _.none()
    }, q = function(e, r) {
        for (var n = 0, t = e.length; n < t; ++n)
            if (e[n] === r)
                return n;
        return -1
    }, X = Array.prototype.push, z = function(e, r) {
        return function(e) {
            for (var r = [], n = 0, t = e.length; n < t; ++n) {
                if (!Array.prototype.isPrototypeOf(e[n]))
                    throw new Error("Arr.flatten item " + n + " was not an array, input: " + e);
                X.apply(r, e[n])
            }
            return r
        }(M(e, r))
    }, H = (Array.prototype.slice,
    R(Array.from) && Array.from,
    Object.keys), W = Object.hasOwnProperty, Y = function(e, r) {
        for (var n = H(e), t = 0, o = n.length; t < o; t++) {
            var i = n[t];
            r(e[i], i, e)
        }
    }, G = function(t, o) {
        var i = {};
        return Y(t, function(e, r) {
            var n = o(e, r, t);
            i[n.k] = n.v
        }),
        i
    }, $ = function(e) {
        return n = function(e) {
            return e
        }
        ,
        t = [],
        Y(e, function(e, r) {
            t.push(n(e, r))
        }),
        t;
        var n, t
    }, K = function(e, r) {
        return Z(e, r) ? _.from(e[r]) : _.none()
    }, Z = function(e, r) {
        return W.call(e, r)
    }, J = (l.Node.ATTRIBUTE_NODE,
    l.Node.CDATA_SECTION_NODE,
    l.Node.COMMENT_NODE,
    l.Node.DOCUMENT_NODE,
    l.Node.DOCUMENT_TYPE_NODE,
    l.Node.DOCUMENT_FRAGMENT_NODE,
    l.Node.ELEMENT_NODE), Q = l.Node.TEXT_NODE, ee = (l.Node.PROCESSING_INSTRUCTION_NODE,
    l.Node.ENTITY_REFERENCE_NODE,
    l.Node.ENTITY_NODE,
    l.Node.NOTATION_NODE,
    i = Q,
    function(e) {
        return e.dom().nodeType === i
    }
    ), re = function(e, r, n) {
        !function(e, r, n) {
            if (!(C(n) || L(n) || P(n)))
                throw l.console.error("Invalid call to Attr.set. Key ", r, ":: Value ", n, ":: Element ", e),
                new Error("Attribute value was not simple");
            e.setAttribute(r, n + "")
        }(e.dom(), r, n)
    }, ne = function(e, r) {
        var n = e.dom().getAttribute(r);
        return null === n ? void 0 : n
    }, te = function(e, r) {
        e.dom().removeAttribute(r)
    }, oe = function(e, r) {
        var n = ne(e, r);
        return void 0 === n || "" === n ? [] : n.split(" ")
    }, ie = function(e) {
        return void 0 !== e.dom().classList
    }, ae = function(e) {
        return oe(e, "class")
    }, ue = function(e, r) {
        return o = r,
        i = oe(n = e, t = "class").concat([o]),
        re(n, t, i.join(" ")),
        !0;
        var n, t, o, i
    }, ce = function(e, r) {
        return o = r,
        0 < (i = B(oe(n = e, t = "class"), function(e) {
            return e !== o
        })).length ? re(n, t, i.join(" ")) : te(n, t),
        !1;
        var n, t, o, i
    }, se = function(e, r) {
        var n;
        ie(e) ? e.dom().classList.remove(r) : ce(e, r),
        0 === (ie(n = e) ? n.dom().classList : ae(n)).length && te(n, "class")
    }, fe = function(e) {
        if (null == e)
            throw new Error("Node cannot be null or undefined");
        return {
            dom: O(e)
        }
    }, le = {
        fromHtml: function(e, r) {
            var n = (r || l.document).createElement("div");
            if (n.innerHTML = e,
            !n.hasChildNodes() || 1 < n.childNodes.length)
                throw l.console.error("HTML does not have a single root node", e),
                new Error("HTML must have a single root node");
            return fe(n.childNodes[0])
        },
        fromTag: function(e, r) {
            var n = (r || l.document).createElement(e);
            return fe(n)
        },
        fromText: function(e, r) {
            var n = (r || l.document).createTextNode(e);
            return fe(n)
        },
        fromDom: fe,
        fromPoint: function(e, r, n) {
            var t = e.dom();
            return _.from(t.elementFromPoint(r, n)).map(fe)
        }
    }, me = function(e, r) {
        e.fire("FormatPainterToggle", {
            state: r
        })
    };
    (a = m || (m = {})).Retrival = "Retrieval",
    a.Application = "Application",
    (u = d || (d = {})).ListSchema = "ListSchema",
    u.SubstitutionSchema = "SubstitionSchema",
    (s = c || (c = {})).InsertUnorderedList = "InsertUnorderedList",
    s.InsertOrderedList = "InsertOrderedList",
    s.InsertDefinitionList = "InsertDefinitionList",
    (f = v || (v = {})).Table = "Table",
    f.Unspecified = "Unspecified";
    var de, ve, pe, ge = function(e) {
        var r, n;
        r = le.fromDom(e.getBody()),
        n = "tox-cursor-format-painter",
        ie(r) ? r.dom().classList.add(n) : ue(r, n)
    }, he = function(e, r) {
        var n;
        n = e,
        se(le.fromDom(n.getBody()), "tox-cursor-format-painter"),
        r.set(m.Retrival),
        me(e, !1)
    }, be = function(e, r) {
        r.get() === m.Application ? he(e, r) : function(r, n) {
            ge(r),
            n.set(m.Application),
            me(r, !0),
            r.execCommand("mceRetrieveFormats");
            var e = function() {
                n.get() === m.Application && (r.execCommand("mcePaintFormats"),
                he(r, n)),
                o()
            }
              , t = function(e) {
                27 === e.keyCode && (he(r, n),
                o())
            };
            r.on("click", e),
            r.on("keydown", t);
            var o = function() {
                r.off("click", e),
                r.off("keydown", t)
            }
        }(e, r)
    }, ye = (void 0 !== l.window ? l.window : Function("return this;")(),
    function() {
        return Se(0, 0)
    }
    ), Se = function(e, r) {
        return {
            major: e,
            minor: r
        }
    }, Oe = {
        nu: Se,
        detect: function(e, r) {
            var n = String(r).toLowerCase();
            return 0 === e.length ? ye() : function(e, r) {
                var n = function(e, r) {
                    for (var n = 0; n < e.length; n++) {
                        var t = e[n];
                        if (t.test(r))
                            return t
                    }
                }(e, r);
                if (!n)
                    return {
                        major: 0,
                        minor: 0
                    };
                var t = function(e) {
                    return Number(r.replace(n, "$" + e))
                };
                return Se(t(1), t(2))
            }(e, n)
        },
        unknown: ye
    }, we = "Firefox", Ne = function(e, r) {
        return function() {
            return r === e
        }
    }, Te = function(e) {
        var r = e.current;
        return {
            current: r,
            version: e.version,
            isEdge: Ne("Edge", r),
            isChrome: Ne("Chrome", r),
            isIE: Ne("IE", r),
            isOpera: Ne("Opera", r),
            isFirefox: Ne(we, r),
            isSafari: Ne("Safari", r)
        }
    }, xe = {
        unknown: function() {
            return Te({
                current: void 0,
                version: Oe.unknown()
            })
        },
        nu: Te,
        edge: O("Edge"),
        chrome: O("Chrome"),
        ie: O("IE"),
        opera: O("Opera"),
        firefox: O(we),
        safari: O("Safari")
    }, Ee = "Windows", ke = "Android", Ae = "Solaris", _e = "FreeBSD", De = function(e, r) {
        return function() {
            return r === e
        }
    }, Ce = function(e) {
        var r = e.current;
        return {
            current: r,
            version: e.version,
            isWindows: De(Ee, r),
            isiOS: De("iOS", r),
            isAndroid: De(ke, r),
            isOSX: De("OSX", r),
            isLinux: De("Linux", r),
            isSolaris: De(Ae, r),
            isFreeBSD: De(_e, r)
        }
    }, Le = {
        unknown: function() {
            return Ce({
                current: void 0,
                version: Oe.unknown()
            })
        },
        nu: Ce,
        windows: O(Ee),
        ios: O("iOS"),
        android: O(ke),
        linux: O("Linux"),
        osx: O("OSX"),
        solaris: O(Ae),
        freebsd: O(_e)
    }, Re = function(e, r) {
        var n = String(r).toLowerCase();
        return U(e, function(e) {
            return e.search(n)
        })
    }, Pe = function(e, n) {
        return Re(e, n).map(function(e) {
            var r = Oe.detect(e.versionRegexes, n);
            return {
                current: e.name,
                version: r
            }
        })
    }, Fe = function(e, n) {
        return Re(e, n).map(function(e) {
            var r = Oe.detect(e.versionRegexes, n);
            return {
                current: e.name,
                version: r
            }
        })
    }, Ie = function(e, r) {
        return -1 !== e.indexOf(r)
    }, je = /.*?version\/\ ?([0-9]+)\.([0-9]+).*/, Me = function(r) {
        return function(e) {
            return Ie(e, r)
        }
    }, Be = [{
        name: "Edge",
        versionRegexes: [/.*?edge\/ ?([0-9]+)\.([0-9]+)$/],
        search: function(e) {
            return Ie(e, "edge/") && Ie(e, "chrome") && Ie(e, "safari") && Ie(e, "applewebkit")
        }
    }, {
        name: "Chrome",
        versionRegexes: [/.*?chrome\/([0-9]+)\.([0-9]+).*/, je],
        search: function(e) {
            return Ie(e, "chrome") && !Ie(e, "chromeframe")
        }
    }, {
        name: "IE",
        versionRegexes: [/.*?msie\ ?([0-9]+)\.([0-9]+).*/, /.*?rv:([0-9]+)\.([0-9]+).*/],
        search: function(e) {
            return Ie(e, "msie") || Ie(e, "trident")
        }
    }, {
        name: "Opera",
        versionRegexes: [je, /.*?opera\/([0-9]+)\.([0-9]+).*/],
        search: Me("opera")
    }, {
        name: "Firefox",
        versionRegexes: [/.*?firefox\/\ ?([0-9]+)\.([0-9]+).*/],
        search: Me("firefox")
    }, {
        name: "Safari",
        versionRegexes: [je, /.*?cpu os ([0-9]+)_([0-9]+).*/],
        search: function(e) {
            return (Ie(e, "safari") || Ie(e, "mobile/")) && Ie(e, "applewebkit")
        }
    }], Ue = [{
        name: "Windows",
        search: Me("win"),
        versionRegexes: [/.*?windows\ nt\ ?([0-9]+)\.([0-9]+).*/]
    }, {
        name: "iOS",
        search: function(e) {
            return Ie(e, "iphone") || Ie(e, "ipad")
        },
        versionRegexes: [/.*?version\/\ ?([0-9]+)\.([0-9]+).*/, /.*cpu os ([0-9]+)_([0-9]+).*/, /.*cpu iphone os ([0-9]+)_([0-9]+).*/]
    }, {
        name: "Android",
        search: Me("android"),
        versionRegexes: [/.*?android\ ?([0-9]+)\.([0-9]+).*/]
    }, {
        name: "OSX",
        search: Me("os x"),
        versionRegexes: [/.*?os\ x\ ?([0-9]+)_([0-9]+).*/]
    }, {
        name: "Linux",
        search: Me("linux"),
        versionRegexes: []
    }, {
        name: "Solaris",
        search: Me("sunos"),
        versionRegexes: []
    }, {
        name: "FreeBSD",
        search: Me("freebsd"),
        versionRegexes: []
    }], Ve = {
        browsers: O(Be),
        oses: O(Ue)
    }, qe = function(e) {
        var r, n, t, o, i, a, u, c, s, f, l, m = Ve.browsers(), d = Ve.oses(), v = Pe(m, e).fold(xe.unknown, xe.nu), p = Fe(d, e).fold(Le.unknown, Le.nu);
        return {
            browser: v,
            os: p,
            deviceType: (n = v,
            t = e,
            o = (r = p).isiOS() && !0 === /ipad/i.test(t),
            i = r.isiOS() && !o,
            a = r.isAndroid() && 3 === r.version.major,
            u = r.isAndroid() && 4 === r.version.major,
            c = o || a || u && !0 === /mobile/i.test(t),
            s = r.isiOS() || r.isAndroid(),
            f = s && !c,
            l = n.isSafari() && r.isiOS() && !1 === /safari/i.test(t),
            {
                isiPad: O(o),
                isiPhone: O(i),
                isTablet: O(c),
                isPhone: O(f),
                isTouch: O(s),
                isAndroid: r.isAndroid,
                isiOS: r.isiOS,
                isWebView: O(l)
            })
        }
    }, Xe = (pe = !(de = function() {
        var e = l.navigator.userAgent;
        return qe(e)
    }
    ),
    function() {
        for (var e = [], r = 0; r < arguments.length; r++)
            e[r] = arguments[r];
        return pe || (pe = !0,
        ve = de.apply(null, e)),
        ve
    }
    ), ze = J, He = (Xe().browser.isIE(),
    function(e, r) {
        var n = e.dom();
        if (n.nodeType !== ze)
            return !1;
        if (void 0 !== n.matches)
            return n.matches(r);
        if (void 0 !== n.msMatchesSelector)
            return n.msMatchesSelector(r);
        if (void 0 !== n.webkitMatchesSelector)
            return n.webkitMatchesSelector(r);
        if (void 0 !== n.mozMatchesSelector)
            return n.mozMatchesSelector(r);
        throw new Error("Browser lacks native selectors")
    }
    ), We = function(e, r, n) {
        for (var t = e.dom(), o = R(n) ? n : O(!1); t.parentNode; ) {
            t = t.parentNode;
            var i = le.fromDom(t);
            if (r(i))
                return _.some(i);
            if (o(i))
                break
        }
        return _.none()
    }, Ye = function(e, r, n) {
        var t, o, i, a, u;
        return t = We,
        a = n,
        u = o = e,
        (i = r)(u) ? _.some(o) : R(a) && a(o) ? _.none() : t(o, i, a)
    }, Ge = {
        formatpainter_checklist: {
            selector: "ul",
            classes: "tox-checklist"
        },
        formatpainter_liststyletype: {
            selector: "ul,ol",
            styles: {
                listStyleType: "%value"
            }
        },
        formatpainter_borderstyle: {
            selector: "td,th",
            styles: {
                borderTopStyle: "%valueTop",
                borderRightStyle: "%valueRight",
                borderBottomStyle: "%valueBottom",
                borderLeftStyle: "%valueLeft"
            },
            remove_similar: !0
        },
        formatpainter_bordercolor: {
            selector: "td,th",
            styles: {
                borderTopColor: "%valueTop",
                borderRightColor: "%valueRight",
                borderBottomColor: "%valueBottom",
                borderLeftColor: "%valueLeft"
            },
            remove_similar: !0
        },
        formatpainter_backgroundcolor: {
            selector: "td,th",
            styles: {
                backgroundColor: "%value"
            },
            remove_similar: !0
        },
        formatpainter_removeformat: [{
            selector: "b,strong,em,i,font,u,strike,sub,sup,dfn,code,samp,kbd,var,cite,mark,q,del,ins",
            remove: "all",
            split: !0,
            expand: !1,
            block_expand: !0,
            deep: !0
        }, {
            selector: "span",
            attributes: ["style", "class"],
            remove: "empty",
            split: !0,
            expand: !1,
            deep: !0
        }, {
            selector: "*:not(tr,td,th,table)",
            attributes: ["style", "class"],
            split: !1,
            expand: !1,
            deep: !0
        }]
    }, $e = function(i, e) {
        return K(e, "selector").exists(function(e) {
            var r = i.getBody()
              , n = i.selection.getStart()
              , t = i.dom.getParents(n, O(!0), r)
              , o = i.selection.getSelectedBlocks();
            return i.dom.is(t.concat(o), e)
        })
    }, Ke = function(t, e) {
        return j(t.formatter.get(e), function(e) {
            return r = t,
            Z(n = e, "inline") && !$e(r, n);
            var r, n
        })
    }, Ze = function(t, e, r) {
        return j(e.get(r), function(e) {
            return r = t,
            Z(n = e, "block") || $e(r, n);
            var r, n
        })
    }, Je = function(e) {
        return 1 < e.length && "%" === e.charAt(0)
    }, Qe = function(e, r) {
        return j(e.formatter.get(r), function(e) {
            return n = K(r = e, "styles").exists(function(e) {
                return j($(e), Je)
            }),
            t = K(r, "attributes").exists(function(e) {
                return j($(e), Je)
            }),
            n || t;
            var r, n, t
        })
    }, er = function(e) {
        return He(e, "OL,UL,DL")
    }, rr = function(e) {
        return He(e, "LI,DT,DD")
    }, nr = function(e, r, n) {
        var t, o = e.formatter, i = Ke(e, n.formatName), a = Ze(e, o, n.formatName), u = (t = n.formatName,
        I(["formatpainter_borderstyle", "formatpainter_bordercolor", "formatpainter_backgroundcolor"], t));
        (r.table && u || r.inline && i || r.block && a && !u) && o.apply(n.formatName, n.substitutedVariables)
    }, tr = function(e, r) {
        return function(e, r) {
            for (var n = [], t = 0; t < e.length; t++) {
                var o = e[t];
                if (!o.isSome())
                    return _.none();
                n.push(o.getOrDie())
            }
            return _.some(r.apply(null, n))
        }([Ye(le.fromDom(e.getStart()), er, r), Ye(le.fromDom(e.getEnd()), er, r)], function(e, r) {
            return n = r,
            e.dom() === n.dom();
            var n
        }).getOr(!1)
    }, or = function(e) {
        var r = e.selection
          , n = r.getRng()
          , t = le.fromDom(e.getBody())
          , o = B(e.selection.getSelectedBlocks().map(le.fromDom), rr)
          , i = n.collapsed && o.length
          , a = o.length && !tr(r, t);
        return 1 < o.length || i || a
    }, ir = function(t, e) {
        var r, n;
        r = t,
        n = e.context,
        r.formatter.remove("formatpainter_removeformat"),
        n === v.Table && function(e, r) {
            for (var n = 0, t = e.length; n < t; n++)
                r(e[n], n, e)
        }(["formatpainter_borderstyle", "formatpainter_bordercolor", "formatpainter_backgroundcolor"], function(e) {
            r.formatter.remove(e)
        }),
        or(r) && r.execCommand("RemoveList");
        var o, i, a, u, c, s, f = (a = (o = t).selection.getStart(),
        u = o.selection.getRng().collapsed,
        c = 0 < o.dom.select("td[data-mce-selected]").length,
        s = !!o.dom.getParent(a, "TABLE"),
        {
            inline: !0,
            table: u && s || c,
            block: u || (i = o.selection,
            1 < i.getSelectedBlocks().length) || c
        });
        e.schemas.forEach(function(e) {
            switch (e.kind) {
            case d.ListSchema:
                r = t,
                n = e,
                f.block && r.execCommand(n.command);
                break;
            case d.SubstitutionSchema:
                nr(t, f, e)
            }
            var r, n
        })
    }, ar = function(e) {
        return ie(e) ? function(e) {
            for (var r = e.dom().classList, n = new Array(r.length), t = 0; t < r.length; t++)
                n[t] = r.item(t);
            return n
        }(e) : ae(e)
    }, ur = function(e, r) {
        var n, t, o = e.dom(), i = l.window.getComputedStyle(o).getPropertyValue(r), a = "" !== i || null != (t = ee(n = e) ? n.dom().parentNode : n.dom()) && t.ownerDocument.body.contains(t) ? i : cr(o, r);
        return null === a ? void 0 : a
    }, cr = function(e, r) {
        return void 0 !== e.style ? e.style.getPropertyValue(r) : ""
    }, sr = function() {
        return (sr = Object.assign || function(e) {
            for (var r, n = 1, t = arguments.length; n < t; n++)
                for (var o in r = arguments[n])
                    Object.prototype.hasOwnProperty.call(r, o) && (e[o] = r[o]);
            return e
        }
        ).apply(this, arguments)
    }, fr = function(o, e) {
        return G(e, function(e, r) {
            return {
                k: e.slice(1, e.length),
                v: (n = o,
                t = r,
                "class" === t ? ar(n).filter(function(e) {
                    return !/^(mce-.*)/.test(e)
                }).join(" ") : ne(n, t))
            };
            var n, t
        })
    }, lr = function(e) {
        return (r = e,
        n = function(e) {
            return 1 < (r = e).length && "%" === r.charAt(0);
            var r
        }
        ,
        t = {},
        o = {},
        Y(r, function(e, r) {
            (n(e, r) ? t : o)[r] = e
        }),
        {
            t: t,
            f: o
        }).t;
        var r, n, t, o
    }, mr = function(e, n) {
        var r = K(e, "styles").map(function(e) {
            return t = n,
            r = lr(e),
            G(r, function(e, r) {
                return {
                    k: e.slice(1, e.length),
                    v: ur(t, (n = r,
                    n.replace(/([A-Z])/g, function(e) {
                        return "-" + e[0].toLowerCase()
                    })))
                };
                var n
            });
            var t, r
        })
          , t = K(e, "attributes").map(function(e) {
            return fr(n, lr(e))
        })
          , o = sr({}, r.getOr({}), t.getOr({}));
        return $(o).every(function(e) {
            return "" !== e
        }) ? _.some(o) : _.none()
    }, dr = function(e, r, n) {
        return (t = e.get(r),
        0 === t.length ? _.none() : _.some(t[0])).bind(function(e) {
            return mr(e, n)
        }).map(function(e) {
            return {
                kind: d.SubstitutionSchema,
                formatName: r,
                substitutedVariables: e
            }
        });
        var t
    }, vr = function(n, t) {
        return (e = n,
        r = e.getParam("formatpainter_blacklisted_formats", "link,address,removeformat,formatpainter_removeformat", "string").split(/[ ,]/),
        H(e.formatter.get()).filter(function(e) {
            return !I(r, e)
        })).filter(function(e) {
            var r = Qe(n, e);
            return n.formatter.matchNode(t.dom(), e, {}, r)
        });
        var e, r
    }, pr = function(e) {
        return (r = e,
        U($(c), function(e) {
            return r.queryCommandState(e)
        })).map(function(e) {
            return {
                kind: d.ListSchema,
                command: e
            }
        });
        var r
    }, gr = function(e) {
        var r, n, t, o, i, a = e.dom, u = e.selection.getStart();
        return {
            schemas: pr(e).toArray().concat((t = e,
            o = u,
            i = t.dom.getParents(o, O(!0)),
            z(M(i, le.fromDom), function(r) {
                return z(vr(t, r), function(e) {
                    return dr(t.formatter, e, r).toArray()
                })
            }))),
            context: (r = a,
            n = u,
            r.getParent(n, "TABLE") ? v.Table : v.Unspecified)
        }
    }, hr = function(e) {
        if (S(tinymce, "4.9.0"))
            return l.window.console.error("The format painter plugin requires at least version 4.9.0 of TinyMCE."),
            {};
        var n, r, t, o, i, a, u, c, s = p(m.Retrival), f = p({
            schemas: [],
            context: v.Unspecified
        });
        return (n = e).on("PreInit", function() {
            Y(Ge, function(e, r) {
                n.formatter.get(r) || n.formatter.register(r, e)
            })
        }),
        t = s,
        o = f,
        (r = e).addCommand("mceToggleFormatPainter", function() {
            be(r, t)
        }),
        r.addCommand("mcePaintFormats", function() {
            r.undoManager.transact(function() {
                ir(r, o.get())
            })
        }),
        r.addCommand("mceRetrieveFormats", function() {
            o.set(gr(r))
        }),
        (i = e).ui ? (u = i).ui.registry.addToggleButton("formatpainter", {
            active: !1,
            icon: "format-painter",
            tooltip: "Format Painter",
            onAction: function() {
                return u.execCommand("mceToggleFormatPainter")
            },
            onSetup: function(r) {
                var e = function(e) {
                    r.setActive(e.state)
                };
                return u.on("FormatPainterToggle", e),
                function() {
                    return u.off("FormatPainterToggle", e)
                }
            }
        }) : (a = i).addButton("formatpainter", {
            active: !1,
            icon: "format-painter",
            tooltip: "Format Painter",
            cmd: "mceToggleFormatPainter",
            onPostRender: function(r) {
                a.on("FormatPainterToggle", function(e) {
                    r.control.active(e.state)
                })
            }
        }),
        (c = e).addShortcut("Meta+alt+C", "", function() {
            c.execCommand("mceRetrieveFormats")
        }),
        c.addShortcut("Meta+alt+V", "", function() {
            c.execCommand("mcePaintFormats")
        }),
        {}
    };
    return function() {
        tinymce.PluginManager.add("formatpainter", hr)
    }
}(window)();
